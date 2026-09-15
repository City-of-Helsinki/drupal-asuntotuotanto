<?php

declare(strict_types=1);

namespace Drupal\asu_application\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserDataInterface;
use Psr\Log\LoggerInterface;

/**
 * Persists application payments from external systems.
 */
final class ApplicationPaymentSyncService {

  private const PAYMENT_MARKS_KEY = 'payment_marks';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
    private readonly UserDataInterface $userData,
  ) {
  }

  /**
   * Upsert incoming payment records.
   *
   * @param array $records
   *   Incoming payload records.
   * @param string $correlationId
   *   Correlation id for logging.
   *
   * @return array
   *   Summary counters.
   */
  public function upsertPayments(array $records, string $correlationId = ''): array {
    $summary = [
      'received' => count($records),
      'created' => 0,
      'updated' => 0,
      'skipped' => 0,
    ];

    $cacheTagsToInvalidate = [];

    try {
      $storage = $this->entityTypeManager->getStorage('asu_application_payment');
    }
    catch (\Throwable $exception) {
      $this->logger->error('Payment sync storage is unavailable. Correlation id: @correlation_id. Error: @error', [
        '@correlation_id' => $correlationId !== '' ? $correlationId : 'n/a',
        '@error' => $exception->getMessage(),
      ]);
      $summary['skipped'] = $summary['received'];
      return $summary;
    }

    foreach ($records as $index => $record) {
      if (!is_array($record)) {
        $summary['skipped']++;
        $this->logger->warning('Skipping payment row @row: payload is not an object. Correlation id: @correlation_id', [
          '@row' => (string) $index,
          '@correlation_id' => $correlationId !== '' ? $correlationId : 'n/a',
        ]);
        continue;
      }

      if (!$this->isSentToSap($record)) {
        $summary['skipped']++;
        continue;
      }

      $applicationId = (int) ($record['application_id'] ?? 0);
      $reservationId = trim((string) ($record['reservation_id'] ?? ''));
      $installmentType = trim((string) ($record['installment_type'] ?? ''));
      $dueDate = $this->normalizeDate((string) ($record['due_date'] ?? ''));
      $amount = $this->normalizeAmount($record['amount'] ?? NULL);

      if (
        $applicationId <= 0 ||
        $reservationId === '' ||
        $installmentType === '' ||
        $dueDate === NULL ||
        $amount === NULL
      ) {
        $summary['skipped']++;
        $this->logger->warning('Skipping invalid payment row @row for application @application_id. Correlation id: @correlation_id', [
          '@row' => (string) $index,
          '@application_id' => (string) $applicationId,
          '@correlation_id' => $correlationId !== '' ? $correlationId : 'n/a',
        ]);
        continue;
      }

      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('application_id', $applicationId)
        ->condition('reservation_id', $reservationId)
        ->condition('installment_type', $installmentType)
        ->range(0, 1)
        ->execute();

      $entity = $ids
        ? $storage->load((int) reset($ids))
        : $storage->create([
          'application_id' => $applicationId,
          'reservation_id' => $reservationId,
          'installment_type' => $installmentType,
        ]);

      $entity->set('amount', $amount);
      $entity->set('due_date', $dueDate);
      $entity->set('account_number', trim((string) ($record['account_number'] ?? '')));
      $entity->set('reference_number', trim((string) ($record['reference_number'] ?? '')));
      $entity->set('sap_sent', TRUE);
      if ($correlationId !== '') {
        $entity->set('source_correlation_id', $correlationId);
      }
      $entity->save();

      if ($ids) {
        $summary['updated']++;
      }
      else {
        $summary['created']++;
      }

      $cacheTagsToInvalidate[] = "asu_application_payment_list:{$applicationId}";
    }

    if ($cacheTagsToInvalidate !== []) {
      Cache::invalidateTags(array_values(array_unique($cacheTagsToInvalidate)));
    }

    return $summary;
  }

  /**
   * List payments for one application sorted by due date ASC.
   */
  public function getPaymentsForApplication(int $applicationId, int $userId = 0): array {
    if ($applicationId <= 0) {
      return [];
    }

    try {
      $storage = $this->entityTypeManager->getStorage('asu_application_payment');
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('application_id', $applicationId)
        ->condition('sap_sent', 1)
        ->sort('due_date', 'ASC')
        ->sort('installment_type', 'ASC')
        ->execute();
    }
    catch (\Throwable) {
      return [];
    }

    if (!$ids) {
      return [];
    }

    $marks = $this->getUserPaymentMarks($userId);

    $payments = [];
    foreach ($storage->loadMultiple($ids) as $payment) {
      $installmentType = (string) $payment->get('installment_type')->value;
      $rawDueDate = (string) $payment->get('due_date')->value;
      $paymentId = (int) $payment->id();
      $markedAt = $marks[$paymentId] ?? 0;
      $payments[] = [
        'payment_id' => $paymentId,
        'installment_type' => $this->formatInstallmentType($installmentType),
        'amount' => number_format((float) $payment->get('amount')->value, 2, ',', ' '),
        'due_date' => $this->formatDateForDisplay($rawDueDate),
        'account_number' => (string) $payment->get('account_number')->value,
        'reference_number' => (string) $payment->get('reference_number')->value,
        'is_marked_paid' => $markedAt > 0,
        'marked_paid_at' => $markedAt > 0 ? date('d.m.Y H:i', $markedAt) : '',
      ];
    }

    return $payments;
  }

  /**
   * Recognize records that were actually sent to SAP.
   */
  private function isSentToSap(array $record): bool {
    $sentFlag = $record['sent_to_sap'] ?? $record['sap_sent'] ?? $record['is_sent_to_sap'] ?? NULL;

    if ($sentFlag === NULL) {
      // Accept by default for backward-compatible payloads where this endpoint
      // is called only after "Lähetä SAP".
      return TRUE;
    }

    return in_array($sentFlag, [TRUE, 1, '1', 'true', 'yes'], TRUE);
  }

  /**
   * Normalize due date to Drupal storage date format (Y-m-d).
   */
  private function normalizeDate(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') {
      return NULL;
    }

    $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
    if ($date instanceof \DateTimeImmutable) {
      return $date->format('Y-m-d');
    }

    try {
      $parsed = new \DateTimeImmutable($raw);
      return $parsed->format('Y-m-d');
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Normalize amount to decimal string with 2 digits.
   */
  private function normalizeAmount(mixed $amount): ?string {
    if ($amount === NULL || $amount === '') {
      return NULL;
    }

    if (is_string($amount)) {
      $amount = str_replace(' ', '', $amount);
      $amount = str_replace(',', '.', $amount);
    }

    if (!is_numeric($amount)) {
      return NULL;
    }

    return number_format((float) $amount, 2, '.', '');
  }

  /**
   * Convert storage date to dd.mm.YYYY for the UI table.
   */
  private function formatDateForDisplay(string $raw): string {
    if ($raw === '') {
      return '-';
    }

    $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
    if (!$date instanceof \DateTimeImmutable) {
      return $raw;
    }

    return $date->format('d.m.Y');
  }

  /**
   * Convert payment installment code to a Finnish UI label.
   */
  private function formatInstallmentType(string $code): string {
    $normalizedCode = strtoupper(trim($code));

    $labels = [
      'PAYMENT_1' => 'Erä 1',
      'PAYMENT_2' => 'Erä 2',
      'PAYMENT_3' => 'Erä 3',
      'PAYMENT_4' => 'Erä 4',
      'PAYMENT_5' => 'Erä 5',
      'PAYMENT_6' => 'Erä 6',
      'PAYMENT_7' => 'Erä 7',
      'REFUND' => 'Hyvitys 1',
      'REFUND_1' => 'Hyvitys 1',
      'REFUND_2' => 'Hyvitys 2',
      'REFUND_3' => 'Hyvitys 3',
      'DOWN_PAYMENT' => 'Käsiraha',
      'LATE_PAYMENT_INTEREST' => 'Viivästyskorko',
      'RIGHT_OF_OCCUPANCY_PAYMENT' => 'AO-maksu 1',
      'RIGHT_OF_OCCUPANCY_PAYMENT_1' => 'AO-maksu 1',
      'RIGHT_OF_OCCUPANCY_PAYMENT_2' => 'AO-maksu 2',
      'RIGHT_OF_OCCUPANCY_PAYMENT_3' => 'AO-maksu 3',
      'FOR_INVOICING' => 'Laskutettava',
      'DEPOSIT' => 'Vakuusmaksu',
      'RESERVATION_FEE' => 'Varausmaksu',
    ];

    return $labels[$normalizedCode] ?? $code;
  }

  /**
   * Mark or unmark one payment as paid for the current user.
   */
  public function setUserPaymentMarked(int $paymentId, int $userId, bool $marked): array {
    if ($paymentId <= 0 || $userId <= 0) {
      return [
        'is_marked_paid' => FALSE,
        'marked_paid_at' => '',
      ];
    }

    $marks = $this->getUserPaymentMarks($userId);

    if ($marked) {
      $marks[$paymentId] = time();
    }
    else {
      unset($marks[$paymentId]);
    }

    $this->userData->set('asu_application', $userId, self::PAYMENT_MARKS_KEY, $marks);

    $markedAt = $marks[$paymentId] ?? 0;

    return [
      'is_marked_paid' => $markedAt > 0,
      'marked_paid_at' => $markedAt > 0 ? date('d.m.Y H:i', $markedAt) : '',
    ];
  }

  /**
   * Ensure payment row belongs to the requested application.
   */
  public function paymentBelongsToApplication(int $paymentId, int $applicationId): bool {
    if ($paymentId <= 0 || $applicationId <= 0) {
      return FALSE;
    }

    try {
      $payment = $this->entityTypeManager
        ->getStorage('asu_application_payment')
        ->load($paymentId);
    }
    catch (\Throwable) {
      return FALSE;
    }

    if (!$payment) {
      return FALSE;
    }

    return (int) $payment->get('application_id')->value === $applicationId;
  }

  /**
   * Load per-user payment marks from user data storage.
   */
  private function getUserPaymentMarks(int $userId): array {
    if ($userId <= 0) {
      return [];
    }

    $marks = $this->userData->get('asu_application', $userId, self::PAYMENT_MARKS_KEY);
    if (!is_array($marks)) {
      return [];
    }

    $normalizedMarks = [];
    foreach ($marks as $paymentId => $markedAt) {
      $id = (int) $paymentId;
      $time = (int) $markedAt;
      if ($id > 0 && $time > 0) {
        $normalizedMarks[$id] = $time;
      }
    }

    return $normalizedMarks;
  }

}
