<?php

declare(strict_types=1);

namespace Drupal\asu_application\Notification;

use Drupal\asu_application\Entity\Application;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Resolves project and salesperson metadata for application messaging flows.
 */
trait ApplicationProjectResolverTrait {

  /**
   * Provides entity type manager from the consuming class.
   */
  abstract protected function getEntityTypeManagerForProjectResolver(): EntityTypeManagerInterface;

  /**
   * Resolves salesperson assigned to the application project.
   */
  private function resolveApplicationSalesperson(Application $application): ?UserInterface {
    $project = $this->loadApplicationProjectEntity($application);
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
   * Resolves project label for notification text.
   */
  private function resolveApplicationProjectLabel(Application $application): string {
    $project = $this->loadApplicationProjectEntity($application);
    return $project ? (string) $project->label() : '';
  }

  /**
   * Resolves recipient mail for salesperson notifications.
   */
  private function resolveApplicationRecipientMail(Application $application, ?UserInterface $salesperson = NULL): string {
    $salesperson ??= $this->resolveApplicationSalesperson($application);
    if ($salesperson && $salesperson->getEmail()) {
      return $salesperson->getEmail();
    }

    return (string) (getenv('DRUPAL_DEFAULT_FORM_EMAIL') ?: '');
  }

  /**
   * Loads project entity associated with an application.
   */
  private function loadApplicationProjectEntity(Application $application): ?object {
    if ($application->hasField('project') && !$application->get('project')->isEmpty()) {
      $project = $application->get('project')->entity;
      if ($project) {
        return $project;
      }
    }

    $projectId = (int) $application->getProjectId();
    if ($projectId <= 0) {
      return NULL;
    }

    return $this->getEntityTypeManagerForProjectResolver()->getStorage('node')->load($projectId);
  }

}
