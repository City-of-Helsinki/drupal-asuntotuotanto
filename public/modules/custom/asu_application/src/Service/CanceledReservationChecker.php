<?php

declare(strict_types=1);

namespace Drupal\asu_application\Service;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_content\Entity\Project;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Detects applications whose Django reservations have all been canceled.
 *
 * Canceling a reservation or rejecting an offer happens in Django only; the
 * Drupal application stays. Such an application no longer blocks the
 * customer from applying to the project again.
 */
final class CanceledReservationChecker {

  /**
   * Django reservation state of a canceled reservation.
   */
  private const CANCELED_STATE = 'canceled';

  /**
   * Whether the current user's reservations are all canceled, per project.
   *
   * @var bool[]
   */
  private array $canceledByProject = [];

  /**
   * Constructs the checker.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Whether every reservation of the list is canceled.
   *
   * @param array $reservations
   *   Reservations from Django's project reservations endpoint.
   *
   * @return bool
   *   TRUE for a non-empty list of canceled reservations only.
   */
  public static function areAllCanceled(array $reservations): bool {
    if ($reservations === []) {
      return FALSE;
    }
    foreach ($reservations as $reservation) {
      if (($reservation['state'] ?? NULL) !== self::CANCELED_STATE) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Whether the current user's sent application has only canceled reservations.
   *
   * Django returns the reservations of the current user only, so other users'
   * applications and drafts (not sent to Django) are never canceled. When
   * Django cannot be reached the application is treated as active.
   *
   * @param \Drupal\asu_application\Entity\Application $application
   *   Application to check.
   *
   * @return bool
   *   TRUE when all of the application's reservations are canceled.
   */
  public function isApplicationCanceled(Application $application): bool {
    if ((int) $application->getOwnerId() !== (int) $this->currentUser->id()) {
      return FALSE;
    }
    if ($application->get('field_backend_id')->getString() === '') {
      return FALSE;
    }

    $projectId = (int) $application->getProjectId();
    if (!array_key_exists($projectId, $this->canceledByProject)) {
      $project = $this->entityTypeManager->getStorage('node')->load($projectId);
      $this->canceledByProject[$projectId] = $project instanceof Project
        && self::areAllCanceled($project->getUserReservations($this->currentUser->id()));
    }
    return $this->canceledByProject[$projectId];
  }

}
