<?php

declare(strict_types=1);

namespace Drupal\asu_application;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationMessage;
use Drupal\user\UserInterface;

/**
 * Handles application message persistence.
 */
final class ApplicationMessageManager {

  /**
   * Constructs the manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Creates and stores a new application message.
   */
  public function createMessage(
    int $applicationId,
    int $projectId,
    string $body,
    string $senderRole,
    ?int $senderUid = NULL,
    ?int $salespersonUid = NULL,
    string $recipientMail = '',
  ): ApplicationMessage {
    /** @var \Drupal\asu_application\Entity\ApplicationMessage $message */
    $message = $this->entityTypeManager->getStorage('asu_application_message')->create([
      'application_id' => $applicationId,
      'project_id' => $projectId,
      'body' => $body,
      'sender_role' => $senderRole,
      'sender_uid' => $senderUid,
      'salesperson_uid' => $salespersonUid,
      'recipient_mail' => $recipientMail,
    ]);
    $message->save();

    return $message;
  }

  /**
   * Loads an application thread in chronological order.
   *
   * @return \Drupal\asu_application\Entity\ApplicationMessage[]
   *   Messages for the given application.
   */
  public function loadThread(int $applicationId): array {
    $ids = $this->entityTypeManager->getStorage('asu_application_message')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('application_id', $applicationId)
      ->sort('created', 'ASC')
      ->sort('id', 'ASC')
      ->execute();

    if ($ids === []) {
      return [];
    }

    /** @var \Drupal\asu_application\Entity\ApplicationMessage[] $messages */
    $messages = $this->entityTypeManager->getStorage('asu_application_message')->loadMultiple($ids);
    return array_values($messages);
  }

  /**
   * Resolves the salesperson assigned to the message application project.
   */
  public function resolveSalesperson(Application $application): ?UserInterface {
    $project = $this->loadProject($application);
    if (!$project) {
      return NULL;
    }

    if (method_exists($project, 'getSalesPerson')) {
      $salesperson = $project->getSalesPerson();
      if ($salesperson instanceof UserInterface) {
        return $salesperson;
      }
    }

    if ($project->hasField('field_salesperson') && !$project->get('field_salesperson')->isEmpty()) {
      $salesperson = $project->get('field_salesperson')->entity;
      if ($salesperson instanceof UserInterface) {
        return $salesperson;
      }
    }

    return NULL;
  }

  /**
   * Resolves the notification recipient email for the application project.
   */
  public function resolveRecipientMail(Application $application): string {
    $salesperson = $this->resolveSalesperson($application);
    if ($salesperson && $salesperson->getEmail()) {
      return $salesperson->getEmail();
    }

    return (string) (getenv('DRUPAL_DEFAULT_FORM_EMAIL') ?: '');
  }

  /**
   * Resolves customer recipients for salesperson notifications.
   *
   * Includes application owner and mapped co-applicant accounts.
   *
   * @return array<int, array{uid:int, mail:string, langcode:string}>
   *   Unique recipients by email.
   */
  public function resolveCustomerRecipients(Application $application, ?string $fallbackCoApplicantEmail = NULL): array {
    $recipients = [];

    $owner = $application->getOwner();
    if ($owner && $owner->getEmail()) {
      $recipients[] = [
        'uid' => (int) $owner->id(),
        'mail' => (string) $owner->getEmail(),
        'langcode' => method_exists($owner, 'getPreferredLangcode')
          ? ($owner->getPreferredLangcode() ?: 'fi')
          : 'fi',
      ];
    }

    $schema = \Drupal::database()->schema();
    if (!$schema->tableExists('asu_application_co_applicant_map')) {
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $applicationId = (int) $application->id();
    if ($applicationId <= 0) {
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $fallbackCoApplicantEmail = trim((string) ($fallbackCoApplicantEmail ?? ''));

    $hasCoApplicantEmailColumn = $schema->fieldExists('asu_application_co_applicant_map', 'co_applicant_email');
    $mapQuery = \Drupal::database()
      ->select('asu_application_co_applicant_map', 'm')
      ->fields('m', ['co_applicant_saml_hash']);

    if ($hasCoApplicantEmailColumn) {
      $mapQuery->fields('m', ['co_applicant_email']);
    }

    $mappingRow = $mapQuery
      ->condition('application_id', $applicationId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($mappingRow) || $mappingRow === []) {
      if (filter_var($fallbackCoApplicantEmail, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = [
          'uid' => 0,
          'mail' => $fallbackCoApplicantEmail,
          'langcode' => 'fi',
        ];
      }
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $coApplicantEmail = trim((string) ($mappingRow['co_applicant_email'] ?? ''));
    if ($hasCoApplicantEmailColumn) {
      if (filter_var($coApplicantEmail, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = [
          'uid' => 0,
          'mail' => $coApplicantEmail,
          'langcode' => 'fi',
        ];
      }
      elseif (filter_var($fallbackCoApplicantEmail, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = [
          'uid' => 0,
          'mail' => $fallbackCoApplicantEmail,
          'langcode' => 'fi',
        ];
        $this->persistCoApplicantEmail($applicationId, $fallbackCoApplicantEmail);
      }

      // When explicit co-applicant email storage is available, avoid
      // hash-based lookup that may match an unrelated local test account.
      return $this->uniqueRecipientsByEmail($recipients);
    }

    if (filter_var($fallbackCoApplicantEmail, FILTER_VALIDATE_EMAIL)) {
      $recipients[] = [
        'uid' => 0,
        'mail' => $fallbackCoApplicantEmail,
        'langcode' => 'fi',
      ];
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $samlHash = $mappingRow['co_applicant_saml_hash'] ?? '';

    if (!is_string($samlHash) || $samlHash === '') {
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['field_saml_hash' => $samlHash]);

    foreach ($users as $user) {
      if (!$user instanceof UserInterface || !$user->getEmail()) {
        continue;
      }

      $recipients[] = [
        'uid' => (int) $user->id(),
        'mail' => (string) $user->getEmail(),
        'langcode' => method_exists($user, 'getPreferredLangcode')
          ? ($user->getPreferredLangcode() ?: 'fi')
          : 'fi',
      ];
    }

    return $this->uniqueRecipientsByEmail($recipients);
  }

  /**
   * Returns the project id for the application.
   */
  public function getProjectId(Application $application): int {
    if ($application->hasField('project') && !$application->get('project')->isEmpty() && $application->get('project')->entity) {
      return (int) $application->get('project')->entity->id();
    }

    return (int) ($application->get('project_id')->value ?? 0);
  }

  /**
   * Returns the application project label.
   */
  public function getProjectLabel(Application $application): string {
    $project = $this->loadProject($application);
    return $project ? (string) $project->label() : '';
  }

  /**
   * Loads the application project entity.
   */
  private function loadProject(Application $application): ?object {
    if ($application->hasField('project') && !$application->get('project')->isEmpty() && $application->get('project')->entity) {
      return $application->get('project')->entity;
    }

    $projectId = (int) ($application->get('project_id')->value ?? 0);
    if ($projectId <= 0) {
      return NULL;
    }

    return $this->entityTypeManager->getStorage('node')->load($projectId);
  }

  /**
   * Removes duplicate recipients by normalized email.
   *
   * @param array<int, array{uid:int, mail:string, langcode:string}> $recipients
   *   Raw recipients.
   *
   * @return array<int, array{uid:int, mail:string, langcode:string}>
   *   Deduplicated recipients.
   */
  private function uniqueRecipientsByEmail(array $recipients): array {
    $unique = [];
    $seen = [];

    foreach ($recipients as $recipient) {
      $mail = mb_strtolower(trim($recipient['mail']));
      if ($mail === '' || isset($seen[$mail])) {
        continue;
      }

      $seen[$mail] = TRUE;
      $recipient['mail'] = $mail;
      $unique[] = $recipient;
    }

    return $unique;
  }

  /**
   * Persist co-applicant email for existing map row when missing.
   */
  private function persistCoApplicantEmail(int $applicationId, string $email): void {
    if ($applicationId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return;
    }

    $database = \Drupal::database();
    $database->update('asu_application_co_applicant_map')
      ->fields([
        'co_applicant_email' => $email,
        'changed' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('application_id', $applicationId)
      ->condition('co_applicant_email', '', '=')
      ->execute();
  }

}
