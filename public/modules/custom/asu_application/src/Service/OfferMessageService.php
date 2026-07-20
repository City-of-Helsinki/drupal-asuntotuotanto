<?php

namespace Drupal\asu_application\Service;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\MarkOfferMessageSentRequest;
use Drupal\asu_api\Api\BackendApi\Request\PendingOfferMessagesRequest;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Polls Django for pending offer emails and sends them to customers.
 */
class OfferMessageService {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly BackendApi $backendApi,
    private readonly OfferNotificationService $offerNotification,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Process pending offer messages on every cron run.
   */
  public function processDueMessages(): void {
    try {
      $response = $this->backendApi->send(new PendingOfferMessagesRequest());
      $messages = $response?->getContent() ?? [];
    }
    catch (\Exception $exception) {
      $this->logger->error(
        'Failed to fetch pending offer messages: @message',
        ['@message' => $exception->getMessage()]
      );
      return;
    }

    foreach ($messages as $message) {
      $this->processSingleMessage($message);
    }
  }

  /**
   * Process a single offer message item.
   */
  private function processSingleMessage(array $message): void {
    $offerId = (int) ($message['id'] ?? 0);
    $subject = (string) ($message['subject'] ?? '');
    $body = (string) ($message['body'] ?? '');
    $recipients = $this->resolveRecipientEmails($message['recipients'] ?? []);
    if ($offerId <= 0 || $subject === '' || $body === '' || $recipients === '') {
      $this->logger->warning(
        'Skipping offer message @id: missing subject, body, or recipients.',
        ['@id' => $offerId]
      );
      return;
    }

    try {
      $sent = $this->offerNotification->sendOfferCreatedNotification(
        $recipients,
        $subject,
        $body,
      );
      if (!$sent) {
        $this->logger->error(
          'Offer message mail failed for offer @id; will retry on next cron.',
          ['@id' => $offerId]
        );
        return;
      }
      $this->backendApi->send(new MarkOfferMessageSentRequest($offerId));
    }
    catch (\Exception $exception) {
      $this->logger->error(
        'Failed offer message for offer @id: @message',
        ['@id' => $offerId, '@message' => $exception->getMessage()]
      );
    }
  }

  /**
   * Build comma-separated recipient list from Django payload.
   */
  private function resolveRecipientEmails(array $recipients): string {
    $emails = [];
    foreach ($recipients as $recipient) {
      if (!is_array($recipient)) {
        continue;
      }
      $email = trim((string) ($recipient['email'] ?? ''));
      if ($email !== '') {
        $emails[] = $email;
      }
    }
    return implode(',', array_unique($emails));
  }

}
