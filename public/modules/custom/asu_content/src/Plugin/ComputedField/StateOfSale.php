<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Computed field ApartmentHoldingType.
 *
 * @ComputedField(
 *   id = "asu_state_of_sale",
 *   label = @Translation("Apartment holding type"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class StateOfSale extends FieldItemList {
  use ComputedSingleItemTrait;

  /**
   * The reverse entity service.
   *
   * @var \Drupal\asu_content\CollectReverseEntity
   */
  protected $reverseEntities;

  /**
   * Constructs a ApartmentHoldingType object.
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
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->reverseEntities = \Drupal::service('asu_content.collect_reverse_entity');
  }

  /**
   * Compute the state of sale value.
   *
   * @return mixed
   *   Returns the computed value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function singleComputeValue() {
    $current_entity = $this->getEntity();
    $reverse_references = $this->reverseEntities->getReverseReferences($current_entity);
    $value = FALSE;

    foreach ($reverse_references as $reference) {
      if (
        !empty($reference) &&
        $reference['referring_entity'] instanceof Node
      ) {
        $reverse_entity = $reference['referring_entity'];
        $id = $reverse_entity->field_state_of_sale->target_id;
        if ($id && $term = Term::load($id)) {
          $value = $term->field_machine_readable_name->value;
        }

      }
    }

    // @todo When displaying the field in twig add value&label through theme.
    // But do make note of search api index before adding theme function.
    return [
      '#markup' => $value,
    ];
  }

}
