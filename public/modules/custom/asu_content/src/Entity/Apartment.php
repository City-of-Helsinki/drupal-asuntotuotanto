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
    if ($this->isNew()) {
      return NULL;
    }

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
   * Create title (full address) for apartment.
   *
   * @result string
   *   Project address and apartment number combined.
   */
  public function createTitle(): string {
    $apartmentNumber = $this->field_apartment_number->value ?? '';
    $project = $this->getProject();

    return isset($project) ?
      "{$project->field_street_address->value} {$apartmentNumber}" : $apartmentNumber;

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
   * Reserve or create application.
   *
   * @return string
   *   Label for the link or button.
   */
  public function getApplicationUrlTitle() {
    $states = [
      'open_for_applications' => 'Create application',
      'free_for_reservations' => 'Reserve',
      'reserved' => 'Reserve',
    ];
    if (
      $this->field_apartment_state_of_sale->target_id &&
      in_array($this->field_apartment_state_of_sale->target_id, array_keys($states))
    ) {
      return $states[$this->field_apartment_state_of_sale->target_id];
    }
    return 'Create application';
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

  /**
   * Is apartment reserved.
   *
   * @return bool
   *   Apartment is reserved.
   */
  public function isReserved(): bool {
    $states = ['reserved', 'reserved_haso'];
    return in_array($this->field_apartment_state_of_sale->target_id, $states);
  }

  /**
   * Is apartment free.
   *
   * @return bool
   *   Apartment is free.
   */
  public function isFree(): bool {
    return $this->field_apartment_state_of_sale->target_id === 'free_for_reservations';
  }

}
