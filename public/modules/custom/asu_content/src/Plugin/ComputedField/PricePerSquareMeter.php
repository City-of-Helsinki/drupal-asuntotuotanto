<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;

/**
 * Computed field PricePerSquareMeter.
 *
 * @ComputedField(
 *   id = "field_price_m2",
 *   label = @Translation("Price per square meter"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class PricePerSquareMeter extends FieldItemList {

  use ComputedSingleItemTrait;

  /**
   * Compute the street address value.
   *
   * @return mixed
   *   Returns the computed value.
   */
  protected function singleComputeValue() {
    $current_entity = $this->getEntity();
    $value = FALSE;

    if (
      $current_entity->hasField('field_living_area') &&
      $current_entity->hasField('field_debt_free_sales_price')
    ) {
      $price = $current_entity->field_debt_free_sales_price->value;
      $living_area = $current_entity->field_living_area->value;

      if (!$price || !$living_area) {
        $value = 0;
      }
      else {
        $value = number_format((float) $price / $living_area, 2, '.', '');
      }
    }

    return [
      '#markup' => $value,
    ];
  }

}
