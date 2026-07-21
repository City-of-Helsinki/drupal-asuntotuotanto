<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;

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
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->reverseEntities = \Drupal::service('asu_content.collect_reverse_entity');
  }

  /**
   * Combine project shared images, apartment images and floorplan once.
   *
   * Order matches SearchMapper: floorplan, shared project images, apartment
   * images. Images are deduplicated by file target_id so repeated reverse
   * project references cannot multiply URLs in Search API / export feeds.
   */
  protected function computeValue() {
    $current_entity = $this->getEntity();
    $reverse_references = $this->reverseEntities->getReverseReferences($current_entity);

    $shared = [];
    $seen_projects = [];
    foreach ($reverse_references as $reference) {
      if (
        empty($reference) ||
        !($reference['referring_entity'] instanceof Node)
      ) {
        continue;
      }

      $referencing_node = $reference['referring_entity'];
      $project_id = $referencing_node->id();
      if (isset($seen_projects[$project_id])) {
        continue;
      }
      $seen_projects[$project_id] = TRUE;

      if (
        $referencing_node->hasField('field_shared_apartment_images') &&
        !$referencing_node->get('field_shared_apartment_images')->isEmpty()
      ) {
        $shared = array_merge(
          $shared,
          $referencing_node->get('field_shared_apartment_images')->getValue()
        );
      }
    }

    $apartment_images = [];
    if (
      $current_entity->hasField('field_images') &&
      !$current_entity->get('field_images')->isEmpty()
    ) {
      $apartment_images = $current_entity->get('field_images')->getValue();
    }

    $floorplan = [];
    if (
      $current_entity->hasField('field_floorplan') &&
      !$current_entity->get('field_floorplan')->isEmpty()
    ) {
      $floorplan = $current_entity->get('field_floorplan')->getValue();
    }

    $images = array_merge($floorplan, $shared, $apartment_images);
    $style = ImageStyle::load('3_2_m');
    if (!$style) {
      return;
    }

    $seen_target_ids = [];
    $delta = 0;
    foreach ($images as $image) {
      if (!isset($image['target_id'])) {
        continue;
      }

      $target_id = (int) $image['target_id'];
      if (isset($seen_target_ids[$target_id])) {
        continue;
      }
      $seen_target_ids[$target_id] = TRUE;

      if ($file = File::load($target_id)) {
        $image_url = $style->buildUrl($file->getFileUri());
        $this->list[$delta] = $this->createItem($delta, $image_url);
        $delta++;
      }
    }
  }

}
