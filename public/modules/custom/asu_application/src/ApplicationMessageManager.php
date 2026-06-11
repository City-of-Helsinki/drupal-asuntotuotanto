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

}
