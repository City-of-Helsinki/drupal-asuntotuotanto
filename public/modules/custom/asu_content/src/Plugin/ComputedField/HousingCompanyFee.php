<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

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
   * Constructs a HousingCompanyFee object.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The data definition.
   * @param string $name
   *   (optional) The name of the created property, or NULL if it is the root
   *   of a typed data tree. Defaults to NULL.
   * @param \Drupal\Core\TypedData\TypedDataInterface $parent
   *   (optional) The parent object of the data property, or NULL if it is the
   *   root of a typed data tree. Defaults to NULL.
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
  }

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
