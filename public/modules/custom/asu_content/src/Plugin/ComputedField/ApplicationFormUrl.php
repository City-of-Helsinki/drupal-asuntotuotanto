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
  public function __construct(
    DataDefinitionInterface $definition,
    $name = NULL,
    TypedDataInterface $parent = NULL) {
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
        $value = '';
        $referencing_node = $reference['referring_entity'];
        $baseurl = \Drupal::request()->getSchemeAndHttpHost();

        // Application url cannot be indexed if:
        // Ownership type or end time is missing.
        if (
        !isset($referencing_node->field_ownership_type->referencedEntities()[0]) ||
        !$referencing_node->field_application_end_time->value
        ) {
          return [
            '#markup' => '',
          ];
        }

        if (
          !$referencing_node->field_application_end_time->value ||
          $this->isBeforeApplicationTimeEnd($referencing_node->field_application_end_time->value)
        ) {
          $apartment_type = strtolower($referencing_node->field_ownership_type->referencedEntities()[0]->getName());
          $value = $baseurl . '/application/add/' . $apartment_type . '/' . $referencing_node->id();
        }
        else {
          $value = $baseurl . '/contact/apply_for_free_apartment?apartment=' . $current_entity->id();
        }
      }
    }

    return [
      '#markup' => $value,
    ];
  }

  /**
   * Check application status.
   *
   * @param string $endTime
   *   End time.
   *
   * @return bool
   *   Application status.
   */
  private function isBeforeApplicationTimeEnd(string $endTime) {
    $end = strtotime($endTime);
    $date = new \DateTime();
    $now = $date->getTimestamp();
    return $now < $end;
  }

}
