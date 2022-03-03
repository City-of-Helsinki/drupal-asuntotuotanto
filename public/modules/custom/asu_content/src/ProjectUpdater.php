<?php

namespace Drupal\asu_content;

use Drupal\asu_content\Entity\Project;

/**
 * Class ProjectUpdater.
 *
 * Update nodes based on the datetimes.
 */
class ProjectUpdater {
  private const APARTMENT_APPLICATION_TARGET_STATE = 'open_for_applications';

  /**
   * Update projects & project's apartments to for sale state.
   *
   * @param \Drupal\asu_content\Entity\Project $project
   *   Project node to update.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateProjectStateToForSale(Project $project) {
    $this->updateApartmentsOpenForApplication($project);
    $project->set('field_state_of_sale', 'for_sale');
    $project->save();
  }

  /**
   * Update projects & project's apartments to reserved state.
   *
   * @param \Drupal\asu_content\Entity\Project $project
   *   Project node to update.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateProjectStateToReserved(Project $project) {
    $reserved = $project->getOwnershipType() == 'haso' ? 'reserved_haso' : 'reserved';
    $this->updateApartmentsReserved($project, $reserved);
    $project->set('field_state_of_sale', 'processing');
    $project->save();
  }

  /**
   * Update apartments state of sale.
   *
   * @param Drupal\asu_content\Entity\Project $project
   *   Project node.
   *
   * @return int|mixed|string|null
   *   Project id.
   */
  private function updateApartmentsOpenForApplication(Project $project) {
    $apartments = $project->getApartmentEntities();
    foreach ($apartments as $apartment) {
      $apartment->field_apartment_state_of_sale = self::APARTMENT_APPLICATION_TARGET_STATE;
      $apartment->save();
    }
    return $project->id();
  }

  /**
   * Update project apartments to new state after application period.
   *
   * @param Drupal\asu_content\Entity\Project $project
   *   Project node.
   *
   * @return int|mixed|string|null
   *   Project id.
   */
  private function updateApartmentsReserved(Project $project, $reserved) {
    $apartments = $project->getApartmentEntities();
    foreach ($apartments as $apartment) {
      $apartment->field_apartment_state_of_sale = $reserved;
      $apartment->save();
    }
    return $project->id();
  }

}
