<?php

namespace Drupal\asu_content\Entity;

use Drupal\node\Entity\Node;

/**
 * Class for node's apartment bundle.
 */
class Apartment extends Node {

  /**
   * Get the parent project for this apartment.
   *
   * @return Drupal\asu_content\Entity\Project|null
   *   Get the project this apartment belongs to.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProject(): ?Project {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'project',
        'field_apartments' => $this->id(),
      ]);
    if (empty($nodes)) {
      return NULL;
    }
    return reset($nodes);
  }

  /**
   * Can an application be sent to apartment.
   *
   * @return bool
   *   Can an application be sent to apartment
   */
  public function isApartmentApplicable(): bool {
    if (!$project = $this->getProject()) {
      return FALSE;
    }

    if (
      $project->isApplicationPeriod() ||
      $project->isApplicationPeriod('after')
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get application url.
   *
   * @return string
   *   Url to application form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getApplicationUrl($apartmentId = NULL): string {
    if (!$project = $this->getProject()) {
      return '';
    }
    return $project->getApplicationUrl($apartmentId);
  }

  /**
   * Is apartment sold.
   *
   * @return bool
   *   Apartment is sold.
   */
  public function isSold(): bool {
    return $this->field_apartment_state_of_sale->target_id === 'sold';
  }

}
