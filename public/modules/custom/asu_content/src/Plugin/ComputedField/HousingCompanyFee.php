<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\Core\Field\FieldItemList;
use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;

/**
 * Computed field HousingCompanyFee.
 *
 * @ComputedField(
 *   id = "field_housing_company_fee",
 *   label = @Translation("Housing company fee"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class HousingCompanyFee extends FieldItemList {

  use ComputedSingleItemTrait;

  /**
   * Compute the housing company fee value.
   *
   * @return mixed
   *   Returns the computed value.
   */
  protected function singleComputeValue() {
    $current_entity = $this->getEntity();
    $value = FALSE;

    if (
      $current_entity->hasField('field_financing_fee') &&
      $current_entity->hasField('field_maintenance_fee')
    ) {
      $financing_fee = $current_entity->field_financing_fee->value;
      $maintenance_fee = $current_entity->field_maintenance_fee->value;
      $value = number_format((float) $financing_fee + $maintenance_fee, 2, '.', '');
    }

    return [
      '#markup' => $value,
    ];
  }

}
