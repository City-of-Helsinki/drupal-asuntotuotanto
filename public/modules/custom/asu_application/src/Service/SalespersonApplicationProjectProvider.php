<?php

declare(strict_types=1);

namespace Drupal\asu_application\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Loads project labels for the salesperson create-application form.
 */
final class SalespersonApplicationProjectProvider {

  /**
   * Constructs a SalespersonApplicationProjectProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Get projects available in the create-application dropdown.
   *
   * Excludes upcoming projects only. Active states such as ready, for_sale,
   * processing and pre_marketing are included for salesperson/admin use.
   *
   * @return array
   *   Projects keyed by node id, each with title, address and ownership_type.
   */
  public function getSelectableProjects(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'project')
      ->condition('status', 1)
      ->condition('field_archived', 0)
      ->condition('field_state_of_sale', 'upcoming', '<>')
      ->sort('field_housing_company', 'ASC')
      ->execute();

    return $this->loadProjectLabels($ids);
  }

  /**
   * Load project title/address/ownership labels for the given node ids.
   *
   * @param array $ids
   *   Project node ids.
   *
   * @return array
   *   Projects keyed by integer node id.
   */
  public function loadProjectLabels(array $ids): array {
    if (!$ids) {
      return [];
    }

    $projects = [];
    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface $project */
    foreach ($storage->loadMultiple($ids) as $project) {
      $ownership_entity = NULL;
      if ($project->hasField('field_ownership_type') && !$project->get('field_ownership_type')->isEmpty()) {
        $ownership_entity = $project->get('field_ownership_type')->entity;
      }
      $projects[(int) $project->id()] = [
        'title' => $project->get('field_housing_company')->value ?? $project->label(),
        'address' => $project->get('field_street_address')->value ?? '',
        'ownership_type' => $ownership_entity ? $ownership_entity->getName() : NULL,
      ];
    }

    return $projects;
  }

}
