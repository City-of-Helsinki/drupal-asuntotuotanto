<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\asu_content\Entity\Apartment;
use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;

/**
 * Computed field StreetAddressField.
 *
 * @ComputedField(
 *   id = "field_apartment_address",
 *   label = @Translation("Street address"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class StreetAddressField extends FieldItemList {

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
    $current_entity = $this->getEntity();
    $value = FALSE;

    if ($current_entity instanceof Apartment) {
      return [
        '#markup' => $current_entity->createTitle(),
      ];
    }
    else {
      return [
        '#markup' => $value,
      ];
    }
  }

}
