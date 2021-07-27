<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Computed field PricePerSquareMeter.
 *
 * @ComputedField(
 *   id = "multiple_values_field",
 *   label = @Translation("Multiple values field"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class MultipleValues extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * The reverse entity service.
   *
   * @var \Drupal\asu_content\CollectReverseEntity
   */
  protected $reverseEntities;

  /**
   * Constructs a ApartmentImages object.
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
    $this->reverseEntities = \Drupal::service('asu_content.collect_reverse_entity');
  }

  /**
   * Compute the street address value.
   *
   * @return mixed
   *   Returns the computed value.
   */
  protected function computeValue() {
    $current_entity = $this->getEntity();
    $reverse_references = $this->reverseEntities->getReverseReferences($current_entity);

    foreach ($reverse_references as $reference) {
      if (
        !empty($reference) &&
        $reference['referring_entity'] instanceof Node
      ) {
        $referencing_node = $reference['referring_entity'];
        $field = $referencing_node->field_services;

        if ($field && !$field->isEmpty()) {
          foreach ($field as $delta => $single_service) {
            if ($service = Term::load($single_service->get('term_id')->getValue())) {
              $distance = $single_service->get('distance')->getValue();
              $data = "{$service->getName()} {$distance}m";
              $this->list[$delta] = $this->createItem($delta, $data);
            }
          }
        }
      }
    }
  }

}
