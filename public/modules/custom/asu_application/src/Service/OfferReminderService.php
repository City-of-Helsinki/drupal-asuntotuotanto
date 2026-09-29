<?php

namespace Drupal\asu_application\Service;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\MarkOfferReminderSentRequest;
use Drupal\asu_api\Api\BackendApi\Request\PendingOfferRemindersRequest;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;

/**
 * Polls Django for due offer reminders and sends salesperson emails.
 */
class OfferReminderService {

  public const STATE_KEY_LAST_RUN = 'asu_application.offer_reminders.last_run_date';

  /**
   * Constructor.
   */
  public function __construct(
    private readonly BackendApi $backendApi,
    private readonly OfferNotificationService $offerNotification,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly StateInterface $state,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Process pending offer reminders if not already run today.
   */
  public function processDueReminders(): void {
    $today = date('Y-m-d');
    if ($this->state->get(self::STATE_KEY_LAST_RUN) === $today) {
      return;
    }

    try {
      $response = $this->backendApi->send(new PendingOfferRemindersRequest());
      $reminders = $response?->getContent() ?? [];
    }
    catch (\Exception $exception) {
      $this->logger->error(
        'Failed to fetch pending offer reminders: @message',
        ['@message' => $exception->getMessage()]
      );
      return;
    }

    foreach ($reminders as $reminder) {
      $this->processSingleReminder($reminder);
    }

    $this->state->set(self::STATE_KEY_LAST_RUN, $today);
  }

  /**
   * Process a single reminder item.
   */
  private function processSingleReminder(array $reminder): void {
    $offerId = (int) ($reminder['id'] ?? 0);
    $projectUuid = (string) ($reminder['project_uuid'] ?? '');
    if ($offerId <= 0 || $projectUuid === '') {
      return;
    }

    $project = $this->entityRepository->loadEntityByUuid('node', $projectUuid);
    if (!$project instanceof NodeInterface) {
      $this->logger->warning(
        'Skipping offer reminder @id: project @uuid not found.',
        ['@id' => $offerId, '@uuid' => $projectUuid]
      );
      return;
    }

    try {
      $this->offerNotification->sendReminderNotification($project, $reminder);
      $this->backendApi->send(new MarkOfferReminderSentRequest($offerId));
    }
    catch (\Exception $exception) {
      $this->logger->error(
        'Failed offer reminder for offer @id: @message',
        ['@id' => $offerId, '@message' => $exception->getMessage()]
      );
    }
  }

}
