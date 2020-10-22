<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Class PricePerSquareMeter.
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
   * Constructs a PricePerSquareMeter object.
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

      if(!$price || !$living_area){
        $value = 0;
      } else {
        $value = number_format((float) $price / $living_area, 2, '.', '');
      }
    }

    return [
      '#markup' => $value,
    ];
  }

}
