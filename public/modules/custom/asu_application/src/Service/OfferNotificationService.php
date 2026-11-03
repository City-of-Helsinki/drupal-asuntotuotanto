<?php

namespace Drupal\asu_application\Service;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_content\Entity\Project;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Sends salesperson notifications about customer offer responses.
 */
class OfferNotificationService {

  use StringTranslationTrait;

  /**
   * Constructor.
   */
  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Notify the project salesperson that a customer accepted an offer.
   */
  public function sendAcceptedNotification(
    Application $application,
    NodeInterface $project,
    array $offerData,
    UserInterface $customer,
  ): void {
    $this->sendNotification(
      'offer_accepted_notification',
      $application,
      $project,
      $offerData,
      $customer,
      (string) $this->t('Customer accepted apartment offer'),
    );
  }

  /**
   * Notify the project salesperson that a customer rejected an offer.
   */
  public function sendRejectedNotification(
    Application $application,
    NodeInterface $project,
    array $offerData,
    UserInterface $customer,
  ): void {
    $this->sendNotification(
      'offer_rejected_notification',
      $application,
      $project,
      $offerData,
      $customer,
      (string) $this->t('Customer rejected apartment offer'),
    );
  }

  /**
   * Notify the project salesperson about an unanswered offer deadline.
   */
  public function sendReminderNotification(
    NodeInterface $project,
    array $reminderData,
  ): void {
    $profile = $reminderData['customer']['primary_profile'] ?? [];
    $apartment = $this->loadApartmentByUuid($reminderData['apartment_uuid'] ?? '');
    $params = [
      'subject' => (string) $this->t('Reminder: apartment offer awaiting customer response'),
      'project_name' => $project->label(),
      'apartment_number' => $apartment?->get('field_apartment_number')->value ?? '-',
      'customer_name' => trim(
        ($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')
      ),
      'customer_email' => $profile['email'] ?? '',
      'valid_until' => $reminderData['valid_until'] ?? '',
      'action' => 'reminder',
    ];
    $this->deliverMail('offer_reminder_notification', $project, $params);
  }

  /**
   * Send a salesperson notification email.
   */
  private function sendNotification(
    string $mailKey,
    Application $application,
    NodeInterface $project,
    array $offerData,
    UserInterface $customer,
    string $subject,
  ): void {
    $apartment = $this->loadApartmentByUuid($offerData['apartment_uuid'] ?? '');
    $params = [
      'subject' => $subject,
      'project_name' => $project->label(),
      'apartment_number' => $apartment?->get('field_apartment_number')->value ?? '-',
      'customer_name' => $customer->getDisplayName(),
      'customer_email' => $customer->getEmail(),
      'valid_until' => $offerData['valid_until'] ?? '',
      'action' => str_contains($mailKey, 'accepted') ? 'accepted' : 'rejected',
      'application_id' => $application->id(),
    ];
    $this->deliverMail($mailKey, $project, $params);
  }

  /**
   * Deliver mail to the project salesperson.
   */
  private function deliverMail(
    string $mailKey,
    NodeInterface $project,
    array $params,
  ): void {
    $recipient = $this->resolveSalespersonEmail($project);
    if (!$recipient) {
      $this->logger->warning(
        'Skipped @key email for project @project: no salesperson email.',
        [
          '@key' => $mailKey,
          '@project' => $project->id(),
        ]
      );
      return;
    }

    $langcode = $this->languageManager->getDefaultLanguage()->getId();
    $this->mailManager->mail(
      'asu_application',
      $mailKey,
      $recipient,
      $langcode,
      $params,
      NULL,
      TRUE,
    );
  }

  /**
   * Resolve salesperson email for a project node.
   */
  private function resolveSalespersonEmail(NodeInterface $project): ?string {
    if (!$project instanceof Project) {
      return NULL;
    }
    $salesperson = $project->getSalesPerson();
    if ($salesperson && $salesperson->getEmail()) {
      return $salesperson->getEmail();
    }
    return NULL;
  }

  /**
   * Load apartment node by UUID.
   */
  private function loadApartmentByUuid(string $uuid): ?NodeInterface {
    if ($uuid === '') {
      return NULL;
    }
    $entity = $this->entityRepository->loadEntityByUuid('node', $uuid);
    return $entity instanceof NodeInterface ? $entity : NULL;
  }

}
