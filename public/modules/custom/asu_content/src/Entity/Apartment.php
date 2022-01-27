<?php
namespace Drupal\asu_content\Entity;

use Drupal\node\Entity\Node;
use Drupal\asu_content\Entity\Project;

class Apartment extends Node {

  /**
   * Get the parent project for this apartment.
   *
   * @return Drupal\asu_content\Entity\Project|null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProject(): ?Project {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
      'type' => 'project',
      'field_apartments' => $this->id()
    ]);
    if (empty($nodes)) {
      return NULL;
    }
    return reset($nodes);
  }

  public function isApartmentApplicable(): bool {
    /** Drupal\asu_content\Entity\Project $project */
    if(!$project = $this->getProject()) {
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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getApplicationUrl($apartmentId = NULL): string {
    /** Drupal\asu_content\Entity\Project $project */
    if (!$project = $this->getProject()) {
      return '';
    }
    return $project->getApplicationUrl($apartmentId);
  }

}
