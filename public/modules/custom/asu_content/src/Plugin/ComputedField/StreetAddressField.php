<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\asu_content\Entity\Apartment;
use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

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
   * Constructs a StreetAddressField object.
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
