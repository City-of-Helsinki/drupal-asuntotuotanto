<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\node\Entity\Node;

/**
 * Computed field StreetAddressField.
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
   * The reverse entity service.
   *
   * @var \Drupal\asu_content\CollectReverseEntity
   */
  protected $reverseEntities;

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
    $this->reverseEntities = \Drupal::service('asu_content.collect_reverse_entity');
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
    $reverse_references = $this->reverseEntities->getReverseReferences($current_entity);
    $value = FALSE;

    foreach ($reverse_references as $reference) {
      if (
        !empty($reference) &&
        $reference['referring_entity'] instanceof Node &&
        $this->getEntity()->hasField('field_apartment_number')
      ) {
        $referencing_node = $reference['referring_entity'];
        $config = \Drupal::config('asu_content.asu_application');
        $baseurl = $config->get('asu_application_form_baseurl');

        if($apartment_type = $config->get('apartment_types')[$referencing_node->field_holding_type->target_id]){
          $apartment_type = $config->get('apartment_types')[$referencing_node->field_holding_type->target_id];
          $value = $baseurl . '/application/add/' . $apartment_type . '/' . $referencing_node->id();
        }
      }
    }

    return [
      '#markup' => $value,
    ];
  }

}
