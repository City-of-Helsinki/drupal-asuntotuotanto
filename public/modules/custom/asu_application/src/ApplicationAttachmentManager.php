<?php

declare(strict_types=1);

namespace Drupal\asu_application;

use PDO;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Manages application message attachments and access checks.
 */
final class ApplicationAttachmentManager {

  /**
   * Default allowed MIME types for customer-visible attachments.
   */
  private const DEFAULT_ALLOWED_MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  ];

  /**
   * Default maximum allowed attachment size in bytes.
   */
  private const DEFAULT_MAX_SIZE_BYTES = 20971520;

  /**
   * Default per-user download attempts within flood window.
   */
  private const DEFAULT_RATE_LIMIT_USER = 30;

  /**
   * Default per-attachment download attempts within flood window.
   */
  private const DEFAULT_RATE_LIMIT_ATTACHMENT = 20;

  /**
   * Default flood window in seconds.
   */
  private const DEFAULT_RATE_LIMIT_WINDOW = 60;

  /**
   * Default per-user upload attempts within flood window.
   */
  private const DEFAULT_UPLOAD_RATE_LIMIT_USER = 20;

  /**
   * Constructs the attachment manager.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
    private readonly FloodInterface $flood,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
  ) {
  }

  /**
   * Stores uploaded file and creates reusable attachment metadata row.
   *
   * @return array<string,mixed>
   *   Upload result payload.
   */
  public function uploadAttachment(
    UploadedFile $uploadedFile,
    int $createdBy,
    string $identifier,
    string $displayName = '',
    array $context = [],
  ): array {
    $window = $this->getRateLimitWindowSeconds();
    $limit = $this->getUploadRateLimitPerUser();
    $floodKey = 'upload-user:' . $createdBy . ':' . $identifier;

    if (!$this->flood->isAllowed('asu_application.attachment_upload.user', $limit, $window, $floodKey)) {
      $this->logUploadDenied($createdBy, 'rate_limited_user', [
        'rate_limit_scope' => 'user',
        'rate_limit' => $limit,
        'window_seconds' => $window,
      ] + $context);
      return [
        'status' => 429,
        'error' => 'rate_limited',
        'code' => 'upload_rate_limited',
        'message' => 'Too many upload attempts.',
      ];
    }
    $this->flood->register('asu_application.attachment_upload.user', $window, $floodKey);

    if (!$uploadedFile->isValid()) {
      $this->logUploadDenied($createdBy, 'invalid_upload_state', $context);
      return [
        'status' => 422,
        'error' => 'invalid_upload',
        'code' => 'upload_failed',
        'message' => 'Upload failed before processing.',
      ];
    }

    $rawMimeType = (string) ($uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType() ?: '');
    $sourcePath = $uploadedFile->getRealPath() ?: $uploadedFile->getPathname();
    $resolvedMime = $this->resolveUploadMimeType(
      $rawMimeType,
      (string) $uploadedFile->getClientOriginalName(),
      $sourcePath,
    );
    if (isset($resolvedMime['error_code'])) {
      $this->logUploadDenied($createdBy, (string) $resolvedMime['error_code'], [
        'mime_type' => (string) ($resolvedMime['mime_type'] ?? ''),
        'raw_mime_type' => $rawMimeType,
      ] + $context);

      return [
        'status' => 422,
        'error' => 'invalid_file',
        'code' => (string) $resolvedMime['error_code'],
        'message' => 'Uploaded file violates attachment policy.',
      ];
    }
    $mimeType = (string) ($resolvedMime['mime_type'] ?? '');
    $sizeBytes = (int) ($uploadedFile->getSize() ?? 0);

    $policyViolation = $this->getAttachmentPolicyViolation([
      'active' => 1,
      'customer_visible' => 1,
      'revoked' => 0,
      'archived' => 0,
      'mime_type' => $mimeType,
      'size_bytes' => $sizeBytes,
    ]);

    if ($policyViolation !== NULL) {
      $this->logUploadDenied($createdBy, $policyViolation, [
        'mime_type' => $mimeType,
        'size_bytes' => $sizeBytes,
      ] + $context);
      return [
        'status' => 422,
        'error' => 'invalid_file',
        'code' => $policyViolation,
        'message' => 'Uploaded file violates attachment policy.',
      ];
    }

    $targetDirectory = 'private://message_attachments';
    $this->fileSystem->prepareDirectory($targetDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    try {
      $contents = file_get_contents($sourcePath);
      if ($contents === FALSE) {
        throw new \RuntimeException('Cannot read uploaded file contents.');
      }

      $storedUri = $this->fileSystem->saveData(
        $contents,
        $targetDirectory . '/' . $this->sanitizeFilename((string) $uploadedFile->getClientOriginalName()),
        FileSystemInterface::EXISTS_RENAME,
      );

      if (!is_string($storedUri) || $storedUri === '') {
        throw new \RuntimeException('File system did not return a stored URI.');
      }

      $storedFile = File::create(['uri' => $storedUri, 'uid' => $createdBy]);
      $storedFile->setFilename((string) basename($storedUri));

      $storedFile->setPermanent();
      $storedFile->save();
    }
    catch (\Throwable $exception) {
      $this->logUploadDenied($createdBy, 'temporary_failure', [
        'exception_message' => $exception->getMessage(),
      ] + $context);

      return [
        'status' => 503,
        'error' => 'temporary_failure',
        'code' => 'storage_write_failed',
        'message' => 'Temporary storage failure.',
      ];
    }

    $normalizedDisplayName = trim($displayName);
    if ($normalizedDisplayName === '') {
      $normalizedDisplayName = (string) $storedFile->getFilename();
    }

    $attachmentId = (int) $this->database->insert('asu_application_attachment')
      ->fields([
        'display_name' => $normalizedDisplayName,
        'mime_type' => $mimeType,
        'size_bytes' => $sizeBytes,
        'storage_uri' => (string) $storedFile->getFileUri(),
        'file_id' => (int) $storedFile->id(),
        'media_id' => 0,
        'active' => 1,
        'customer_visible' => 1,
        'revoked' => 0,
        'archived' => 0,
        'created' => $this->time->getRequestTime(),
        'created_by' => $createdBy,
      ])
      ->execute();

    return [
      'status' => 201,
      'file_id' => (int) $storedFile->id(),
      'attachment_id' => $attachmentId,
      'display_name' => $normalizedDisplayName,
      'mime_type' => $mimeType,
      'size_bytes' => $sizeBytes,
    ];
  }

  /**
   * Validates that attachment ids are usable in customer messaging.
   *
   * @param int[] $attachmentIds
   *   Attachment ids from API payload.
   *
   * @return array{valid_ids:int[],errors:array<int,string>}
   *   Validation result split by valid ids and invalid id reasons.
   */
  public function validateAttachableIds(array $attachmentIds): array {
    if ($attachmentIds === []) {
      return ['valid_ids' => [], 'errors' => []];
    }

    $ids = array_values(array_unique(array_map('intval', $attachmentIds)));

    $rows = $this->database->select('asu_application_attachment', 'a')
      ->fields('a')
      ->condition('id', $ids, 'IN')
      ->execute()
      ->fetchAllAssoc('id', PDO::FETCH_ASSOC);

    $valid = [];
    $errors = [];

    foreach ($ids as $id) {
      $row = $rows[$id] ?? NULL;
      if (!is_array($row)) {
        $errors[$id] = 'attachment_not_found';
        continue;
      }

      $violation = $this->getAttachmentPolicyViolation($row);
      if ($violation !== NULL) {
        $errors[$id] = $violation;
        continue;
      }

      $valid[] = $id;
    }

    return [
      'valid_ids' => $valid,
      'errors' => $errors,
    ];
  }

  /**
   * Creates new reusable attachment records from existing Drupal files.
   *
   * @param array<int, array<string, mixed>> $items
   *   New attachment payload items.
   * @param int $createdBy
   *   User id of creator.
   *
   * @return array{created_ids:int[],errors:array<int,string>}
   *   Created attachment ids and item-level errors.
   */
  public function createAttachments(array $items, int $createdBy): array {
    if ($items === []) {
      return ['created_ids' => [], 'errors' => []];
    }

    $createdIds = [];
    $errors = [];
    $timestamp = $this->time->getRequestTime();

    foreach ($items as $index => $item) {
      if (!is_array($item)) {
        $errors[(int) $index] = 'invalid_item';
        continue;
      }

      $fileId = (int) ($item['file_id'] ?? 0);
      if ($fileId <= 0) {
        $errors[(int) $index] = 'missing_file_id';
        continue;
      }

      $file = File::load($fileId);
      if (!$file instanceof File) {
        $errors[(int) $index] = 'file_not_found';
        continue;
      }

      $mimeType = (string) ($file->getMimeType() ?: '');
      $sizeBytes = (int) ($file->getSize() ?? 0);
      $displayName = trim((string) ($item['display_name'] ?? $file->getFilename()));

      $policyViolation = $this->getAttachmentPolicyViolation([
        'active' => 1,
        'customer_visible' => (int) ($item['customer_visible'] ?? 1),
        'revoked' => 0,
        'archived' => 0,
        'mime_type' => $mimeType,
        'size_bytes' => $sizeBytes,
      ]);

      if ($policyViolation !== NULL) {
        $errors[(int) $index] = $policyViolation;
        continue;
      }

      $this->database->insert('asu_application_attachment')
        ->fields([
          'display_name' => $displayName !== '' ? $displayName : $file->getFilename(),
          'mime_type' => $mimeType,
          'size_bytes' => $sizeBytes,
          'storage_uri' => (string) $file->getFileUri(),
          'file_id' => $fileId,
          'media_id' => (int) ($item['media_id'] ?? 0),
          'active' => 1,
          'customer_visible' => (int) ($item['customer_visible'] ?? 1) === 1 ? 1 : 0,
          'revoked' => 0,
          'archived' => 0,
          'created' => $timestamp,
          'created_by' => $createdBy,
        ])
        ->execute();

      $createdIds[] = (int) $this->database->query('SELECT MAX(id) FROM {asu_application_attachment}')->fetchField();
    }

    return [
      'created_ids' => $createdIds,
      'errors' => $errors,
    ];
  }

  /**
   * Attaches reusable attachments to a message.
   *
   * @param int $messageId
   *   Message id.
   * @param int[] $attachmentIds
   *   Attachment ids.
   * @param int $attachedBy
   *   User id that performed linking.
   */
  public function attachToMessage(int $messageId, array $attachmentIds, int $attachedBy): void {
    if ($messageId <= 0 || $attachmentIds === []) {
      return;
    }

    $attachmentIds = array_values(array_unique(array_map('intval', $attachmentIds)));
    $timestamp = $this->time->getRequestTime();

    foreach ($attachmentIds as $delta => $attachmentId) {
      if ($attachmentId <= 0) {
        continue;
      }

      $this->database->merge('asu_application_message_attachment')
        ->keys([
          'message_id' => $messageId,
          'attachment_id' => $attachmentId,
        ])
        ->fields([
          'ordering' => $delta,
          'attached_at' => $timestamp,
          'attached_by' => $attachedBy,
        ])
        ->execute();

      $this->logger->notice('attachment_attached_to_message', [
        'event' => 'attachment_attached_to_message',
        'message_id' => $messageId,
        'attachment_id' => $attachmentId,
        'attached_by' => $attachedBy,
      ]);
    }
  }

  /**
   * Builds attachment payload by message id for API responses.
   *
   * @param int[] $messageIds
   *   Message ids.
   *
   * @return array<int, array<int, array<string, mixed>>>
   *   Attachments keyed by message id.
   */
  public function getAttachmentsByMessageIds(array $messageIds): array {
    $result = [];
    $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn (int $id): bool => $id > 0)));

    if ($normalizedIds === []) {
      return $result;
    }

    $query = $this->database->select('asu_application_message_attachment', 'ma');
    $query->innerJoin('asu_application_attachment', 'a', 'a.id = ma.attachment_id');
    $query->fields('ma', ['message_id', 'attachment_id', 'ordering']);
    $query->fields('a', ['display_name', 'mime_type', 'size_bytes', 'active', 'customer_visible', 'revoked', 'archived']);
    $query->condition('ma.message_id', $normalizedIds, 'IN');
    $query->orderBy('ma.message_id', 'ASC');
    $query->orderBy('ma.ordering', 'ASC');
    $query->orderBy('ma.attached_at', 'ASC');

    $rows = $query->execute()->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      if ($this->getAttachmentPolicyViolation($row) !== NULL) {
        continue;
      }

      $messageId = (int) $row['message_id'];
      $attachmentId = (int) $row['attachment_id'];

      $result[$messageId][] = [
        'id' => $attachmentId,
        'display_name' => (string) $row['display_name'],
        'mime_type' => (string) $row['mime_type'],
        'size_bytes' => (int) $row['size_bytes'],
        'download_url' => '/user/application/attachments/' . $attachmentId . '/download',
      ];
    }

    return $result;
  }

  /**
   * Resolves downloadable file and ACL result.
   *
   * @return array<string, mixed>
   *   Status and payload.
   */
  public function resolveDownload(int $attachmentId, AccountInterface $account, string $identifier): array {
    if ($attachmentId <= 0) {
      return ['status' => 404, 'error' => 'not_found'];
    }

    $window = $this->getRateLimitWindowSeconds();
    $uid = (int) $account->id();
    $userLimit = $this->getRateLimitPerUser();
    $attachmentLimit = $this->getRateLimitPerAttachment();

    $userKey = 'user:' . $uid . ':' . $identifier;
    if (!$this->flood->isAllowed('asu_application.attachment_download.user', $userLimit, $window, $userKey)) {
      $this->logDenied($attachmentId, $account, 'rate_limited_user', [
        'rate_limit_scope' => 'user',
        'rate_limit' => $userLimit,
        'window_seconds' => $window,
      ]);
      return ['status' => 429, 'error' => 'rate_limited'];
    }

    $attachmentKey = 'attachment:' . $attachmentId . ':uid:' . $uid;
    if (!$this->flood->isAllowed('asu_application.attachment_download.attachment', $attachmentLimit, $window, $attachmentKey)) {
      $this->logDenied($attachmentId, $account, 'rate_limited_attachment', [
        'rate_limit_scope' => 'attachment',
        'rate_limit' => $attachmentLimit,
        'window_seconds' => $window,
      ]);
      return ['status' => 429, 'error' => 'rate_limited'];
    }

    $this->flood->register('asu_application.attachment_download.user', $window, $userKey);
    $this->flood->register('asu_application.attachment_download.attachment', $window, $attachmentKey);

    $row = $this->database->select('asu_application_attachment', 'a')
      ->fields('a')
      ->condition('id', $attachmentId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row)) {
      $this->logDenied($attachmentId, $account, 'attachment_not_found');
      return ['status' => 404, 'error' => 'not_found'];
    }

    $violation = $this->getAttachmentPolicyViolation($row);
    if ($violation !== NULL) {
      $this->logDenied($attachmentId, $account, $violation);
      return ['status' => 404, 'error' => 'not_found'];
    }

    $messageIds = $this->database->select('asu_application_message_attachment', 'ma')
      ->fields('ma', ['message_id'])
      ->condition('attachment_id', $attachmentId)
      ->execute()
      ->fetchCol();

    if ($messageIds === []) {
      $this->logDenied($attachmentId, $account, 'missing_message_context');
      return ['status' => 403, 'error' => 'forbidden'];
    }

    if (!$this->userCanAccessAtLeastOneMessage($account, array_map('intval', $messageIds))) {
      $this->logDenied($attachmentId, $account, 'message_access_denied');
      return ['status' => 403, 'error' => 'forbidden'];
    }

    $file = File::load((int) $row['file_id']);
    if (!$file instanceof File || $file->getFileUri() === '') {
      $this->logDenied($attachmentId, $account, 'file_missing');
      return ['status' => 404, 'error' => 'not_found'];
    }

    $this->logger->notice('attachment_opened', [
      'event' => 'attachment_opened',
      'attachment_id' => $attachmentId,
      'uid' => (int) $account->id(),
      'file_id' => (int) $row['file_id'],
    ]);

    return [
      'status' => 200,
      'file' => $file,
      'display_name' => (string) $row['display_name'],
      'mime_type' => (string) $row['mime_type'],
      'size_bytes' => (int) $row['size_bytes'],
    ];
  }

  /**
   * Marks an attachment as revoked and logs audit event.
   */
  public function revokeAttachment(int $attachmentId, int $revokedBy): bool {
    if ($attachmentId <= 0) {
      return FALSE;
    }

    $updated = $this->database->update('asu_application_attachment')
      ->fields([
        'revoked' => 1,
        'active' => 0,
      ])
      ->condition('id', $attachmentId)
      ->execute();

    if ($updated > 0) {
      $this->logger->notice('attachment_revoked', [
        'event' => 'attachment_revoked',
        'attachment_id' => $attachmentId,
        'uid' => $revokedBy,
      ]);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if account can access at least one message in given context.
   *
   * @param int[] $messageIds
   *   Message ids.
   */
  private function userCanAccessAtLeastOneMessage(AccountInterface $account, array $messageIds): bool {
    if ($account->hasPermission('administer applications')) {
      return TRUE;
    }

    if ($messageIds === []) {
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage('asu_application_message');
    $messages = $storage->loadMultiple($messageIds);

    $accountEmail = '';
    if ($account->isAuthenticated()) {
      $user = $this->entityTypeManager->getStorage('user')->load($account->id());
      if ($user instanceof UserInterface) {
        $accountEmail = mb_strtolower(trim((string) $user->getEmail()));
      }
    }

    foreach ($messages as $message) {
      if ($message->hasField('sender_uid') && !$message->get('sender_uid')->isEmpty()) {
        if ((int) $message->get('sender_uid')->target_id === (int) $account->id()) {
          return TRUE;
        }
      }

      if ($accountEmail !== '' && $message->hasField('recipient_mail')) {
        $recipientMail = mb_strtolower(trim((string) $message->get('recipient_mail')->value));
        if ($recipientMail !== '' && $recipientMail === $accountEmail) {
          return TRUE;
        }
      }

      $applicationId = (int) ($message->get('application_id')->value ?? 0);
      if ($applicationId <= 0) {
        continue;
      }

      try {
        $application = $this->entityTypeManager->getStorage('asu_application')->load($applicationId);
        if ($application && $application->access('view', $account, TRUE)->isAllowed()) {
          return TRUE;
        }
      }
      catch (\Throwable) {
        // Fail closed: if the context entity cannot be loaded we do not grant
        // access based on this fallback path.
      }
    }

    return FALSE;
  }

  /**
   * Returns policy violation code or NULL when attachment is valid.
   *
   * @param array<string, mixed> $row
   *   Attachment row.
   */
  private function getAttachmentPolicyViolation(array $row): ?string {
    if ((int) ($row['active'] ?? 0) !== 1) {
      return 'inactive';
    }
    if ((int) ($row['customer_visible'] ?? 0) !== 1) {
      return 'customer_hidden';
    }
    if ((int) ($row['revoked'] ?? 0) === 1) {
      return 'revoked';
    }
    if ((int) ($row['archived'] ?? 0) === 1) {
      return 'archived';
    }

    $mimeType = (string) ($row['mime_type'] ?? '');
    if ($mimeType === '' || !in_array($mimeType, $this->getAllowedMimeTypes(), TRUE)) {
      return 'mime_not_allowed';
    }

    $sizeBytes = (int) ($row['size_bytes'] ?? 0);
    if ($sizeBytes <= 0 || $sizeBytes > $this->getMaxSizeBytes()) {
      return 'size_exceeded';
    }

    return NULL;
  }

  /**
   * Logs denied attachment access attempt.
   */
  private function logDenied(int $attachmentId, AccountInterface $account, string $reason, array $context = []): void {
    $this->logger->warning('attachment_download_denied', $context + [
      'event' => 'attachment_download_denied',
      'attachment_id' => $attachmentId,
      'uid' => (int) $account->id(),
      'reason' => $reason,
    ]);
  }

  /**
   * Logs denied attachment upload attempt.
   */
  private function logUploadDenied(int $uid, string $reason, array $context = []): void {
    $this->logger->warning('attachment_upload_denied', $context + [
      'event' => 'attachment_upload_denied',
      'uid' => $uid,
      'reason' => $reason,
    ]);
  }

  /**
   * Sanitizes a filename for storage.
   */
  private function sanitizeFilename(string $filename): string {
    $filename = trim($filename);
    if ($filename === '') {
      return 'attachment.bin';
    }

    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? '';
    $safe = ltrim($safe, '.');

    return $safe !== '' ? $safe : 'attachment.bin';
  }

  /**
   * Resolves upload MIME using content signature for generic MIME values.
   *
   * @return array{mime_type:string,error_code?:string}
   *   Resolved MIME or error code when validation fails.
   */
  private function resolveUploadMimeType(string $mimeType, string $filename, string $sourcePath): array {
    $normalizedMime = $this->canonicalizeMimeType($mimeType);
    $genericMimeValues = ['', 'application/octet-stream', 'binary/octet-stream'];

    if (!in_array($normalizedMime, $genericMimeValues, TRUE)) {
      return ['mime_type' => $normalizedMime];
    }

    $detectedMimeType = $this->detectMimeTypeBySignature($sourcePath);
    if (in_array($detectedMimeType, $genericMimeValues, TRUE)) {
      return [
        'mime_type' => $detectedMimeType,
        'error_code' => 'mime_unverifiable',
      ];
    }

    $expectedByExtension = $this->expectedMimeByExtension($filename);
    if ($expectedByExtension !== '' && $expectedByExtension !== $detectedMimeType) {
      return [
        'mime_type' => $detectedMimeType,
        'error_code' => 'mime_extension_mismatch',
      ];
    }

    return ['mime_type' => $detectedMimeType];
  }

  /**
   * Detects MIME type from content signature.
   */
  private function detectMimeTypeBySignature(string $sourcePath): string {
    if ($sourcePath === '' || !is_readable($sourcePath)) {
      return '';
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->file($sourcePath);

    if (!is_string($detected)) {
      return '';
    }

    return $this->canonicalizeMimeType($detected);
  }

  /**
   * Canonicalizes MIME aliases used by finfo and client uploads.
   */
  private function canonicalizeMimeType(string $mimeType): string {
    $normalized = strtolower(trim($mimeType));
    $aliases = [
      'application/x-pdf' => 'application/pdf',
      'image/jpg' => 'image/jpeg',
      'text/x-log' => 'text/plain',
    ];

    return $aliases[$normalized] ?? $normalized;
  }

  /**
   * Returns expected MIME type by extension when known.
   */
  private function expectedMimeByExtension(string $filename): string {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $extensionToMime = [
      'pdf' => 'application/pdf',
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png' => 'image/png',
      'txt' => 'text/plain',
      'doc' => 'application/msword',
      'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    return $extensionToMime[$extension] ?? '';
  }

  /**
   * Returns current allowed mime types from configuration.
   *
   * @return string[]
   *   Mime type list.
   */
  private function getAllowedMimeTypes(): array {
    $mimeTypes = $this->configFactory
      ->get('asu_application.settings')
      ->get('attachments.allowed_mime_types');

    if (!is_array($mimeTypes) || $mimeTypes === []) {
      return self::DEFAULT_ALLOWED_MIME_TYPES;
    }

    $normalized = [];
    foreach ($mimeTypes as $mimeType) {
      $mimeType = trim((string) $mimeType);
      if ($mimeType !== '') {
        $normalized[] = $mimeType;
      }
    }

    return $normalized !== [] ? $normalized : self::DEFAULT_ALLOWED_MIME_TYPES;
  }

  /**
   * Returns current max attachment size in bytes.
   */
  private function getMaxSizeBytes(): int {
    $value = (int) $this->configFactory
      ->get('asu_application.settings')
      ->get('attachments.max_file_size_bytes');

    return $value > 0 ? $value : self::DEFAULT_MAX_SIZE_BYTES;
  }

  /**
   * Returns per-user flood limit.
   */
  private function getRateLimitPerUser(): int {
    $value = (int) $this->configFactory
      ->get('asu_application.settings')
      ->get('attachments.rate_limit.per_user');

    return $value > 0 ? $value : self::DEFAULT_RATE_LIMIT_USER;
  }

  /**
   * Returns per-attachment flood limit.
   */
  private function getRateLimitPerAttachment(): int {
    $value = (int) $this->configFactory
      ->get('asu_application.settings')
      ->get('attachments.rate_limit.per_attachment');

    return $value > 0 ? $value : self::DEFAULT_RATE_LIMIT_ATTACHMENT;
  }

  /**
   * Returns flood window in seconds.
   */
  private function getRateLimitWindowSeconds(): int {
    $value = (int) $this->configFactory
      ->get('asu_application.settings')
      ->get('attachments.rate_limit.window_seconds');

    return $value > 0 ? $value : self::DEFAULT_RATE_LIMIT_WINDOW;
  }

  /**
   * Returns per-user upload flood limit.
   */
  private function getUploadRateLimitPerUser(): int {
    $value = (int) $this->configFactory
      ->get('asu_application.settings')
      ->get('attachments.rate_limit.upload_per_user');

    return $value > 0 ? $value : self::DEFAULT_UPLOAD_RATE_LIMIT_USER;
  }

}
