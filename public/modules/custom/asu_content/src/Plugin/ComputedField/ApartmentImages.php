<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Combines shared images from project and apartment images.
 *
 * @ComputedField(
 *   id = "asu_computed_apartment_images",
 *   label = @Translation("All apartment images"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class ApartmentImages extends FieldItemList {
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
   * Combine Project shared apartment images and images.
   *
   * @return mixed
   *   Returns the computed value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */

  /**
   * Protected function singleComputeValue().
   */
  protected function computeValue() {
    $current_entity = $this->getEntity();
    $reverse_references = $this->reverseEntities->getReverseReferences($current_entity);

    $images = [];

    foreach ($reverse_references as $reference) {
      if (
        !empty($reference) &&
        $reference['referring_entity'] instanceof Node
      ) {
        $referencing_node = $reference['referring_entity'];
        // Add shared images from project.
        if (!$referencing_node->field_shared_apartment_images->isEmpty()) {
          $images = array_merge($images, $referencing_node->field_shared_apartment_images->getValue());
        }
      }
      // Add images from apartment.
      if (!$current_entity->field_images->isEmpty()) {
        $images = array_merge($images, $current_entity->field_images->getValue());
      }
      // Floorplan should be the first item in images array.
      if (!$current_entity->field_floorplan->isEmpty()) {
        $images = array_merge($current_entity->field_floorplan->getValue(), $images);
      }

      foreach ($images as $delta => $image) {
        if (!isset($image['target_id'])) {
          continue;
        }

        if ($file = File::load($image['target_id'])) {
          $style = ImageStyle::load('3_2_m');
          $image_url = $style->buildUrl($file->uri->value);
          $this->list[$delta] = $this->createItem($delta, $image_url);
        }
      }
    }

  }

}
