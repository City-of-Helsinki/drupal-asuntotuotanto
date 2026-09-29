<?php

declare(strict_types=1);

namespace Drupal\asu_application\Service;

use Drupal\asu_application\Entity\Application;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Detects whether an application is a late (jälkihakemus) submission.
 */
final class LateApplicationChecker {

  /**
   * Constructs a LateApplicationChecker object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Whether the application was created after the project application period.
   *
   * Matches the jälkihakemus logic used in application sync summaries.
   *
   * @param \Drupal\asu_application\Entity\Application $application
   *   The application entity.
   *
   * @return bool
   *   TRUE when the application is a late submission.
   */
  public function isLateSubmission(Application $application): bool {
    $project = $this->entityTypeManager->getStorage('node')->load(
      $application->getProjectId(),
    );
    if (!$project instanceof NodeInterface) {
      return FALSE;
    }

    if (!$project->hasField('field_application_end_time')
      || $project->get('field_application_end_time')->isEmpty()) {
      return FALSE;
    }

    $end_iso = (string) $project->get('field_application_end_time')->value;
    if ($end_iso === '') {
      return FALSE;
    }

    $end_timestamp = strtotime($end_iso);
    if ($end_timestamp === FALSE) {
      return FALSE;
    }

    $created = (int) ($application->get('created')->value ?? 0);
    return $created > $end_timestamp;
  }

}
