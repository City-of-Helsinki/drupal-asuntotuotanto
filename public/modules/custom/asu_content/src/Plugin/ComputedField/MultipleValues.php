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
    $ids = &drupal_static(__FUNCTION__);
    $current_entity = $this->getEntity();
    $reverse_references = $this->reverseEntities->getReverseReferences($current_entity);

    foreach ($reverse_references as $reference) {
      if (isset($ids[$reference['referring_entity_id']])) {
        return;
      }

      if (
        !empty($reference) &&
        $reference['referring_entity'] instanceof Node
      ) {
        $referencing_node = $reference['referring_entity'];
        $termIds = [];
        $distances = [];
        $fieldServices = $referencing_node->field_services->getValue();
        $fieldServicesValues = array_keys(array_column($fieldServices, 'term_id'), 0);

        foreach ($fieldServicesValues as $key) {
          unset($fieldServices[$key]);
        }

        if (count($fieldServices) > 0) {
          $fieldServices = array_values($fieldServices);

          foreach ($fieldServices as $delta => $fieldService) {
            $termId = $fieldService['term_id'];
            if (!empty($termId) && $termId != '0') {
              $termIds[$delta] = $termId;
              $distances[$termId] = $fieldService['distance'];
            }

            if (!empty($termIds) && count($termIds) > 0) {
              $terms = Term::loadMultiple($termIds);

              foreach ($terms as $delta => $term) {
                $distance = $distances[$term->id()];
                $data = "{$term->getName()} {$distance}m";
                $this->list[$delta] = $this->createItem($delta, $data);
              }
            }
          }
        }

        $ids[$reference['referring_entity_id']] = $reference['referring_entity_id'];
      }

    }
  }

}
