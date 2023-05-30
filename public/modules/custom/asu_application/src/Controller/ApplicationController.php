<?php

namespace Drupal\asu_application\Controller;

use Drupal\asu_content\Entity\Project;
use Drupal\Core\Access\AccessResult;
use Drupal\node\Entity\Node;

/**
 * Application controller.
 */
class ApplicationController {

  /**
   * A custom access check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access($application_type, $project_id) {
    if (!$project_id) {
      return AccessResult::forbidden();
    }

    /** @var Project $project */
    $project = Node::load($project_id);

    if ($project->isApplicationPeriod()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
