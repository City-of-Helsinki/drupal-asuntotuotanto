<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\asu_content\Entity\Apartment;
use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;

/**
 * Computed field ApplicationFormUrl.
 *
 * @ComputedField(
 *   id = "asu_application_form_url",
 *   label = @Translation("Application form url"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class ApplicationFormUrl extends FieldItemList {
  use ComputedSingleItemTrait;

  /**
   * Compute the street address value.
   *
   * @return mixed
   *   Returns the computed value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function singleComputeValue() {
    $apartment = $this->getEntity();
    if (!$apartment instanceof Apartment ||
        $apartment->getProject()
    ) {
      return [
        '#markup' => '',
      ];
    }
    // Only triggers when the apartment is free for reservation
    // when application time has ended and is apartment is free.
    // Strip spaces out of a apartment number value.
    $apartment_number = trim(str_replace(' ', '', $apartment->field_apartment_number->value));
    return [
      '#markup' => $apartment->getApplicationUrl($apartment_number),
    ];
  }

}
