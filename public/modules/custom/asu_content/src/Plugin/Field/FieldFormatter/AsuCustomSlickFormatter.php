<?php

namespace Drupal\asu_content\Plugin\Field\FieldFormatter;

use Drupal\node\Entity\Node;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\slick\Plugin\Field\FieldFormatter\SlickImageFormatter;

/**
 * Combine floorplan_image with field_images in order to show them in slick carousel.
 *
 * @FieldFormatter(
 *   id = "asu_custom_slick_formatter",
 *   label = @Translation("Custom slick formatter"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class AsuCustomSlickFormatter extends SlickImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $node = $items->getParent()->getEntity();

    $parent_node_results = \Drupal::entityTypeManager()
      ->getListBuilder('node')
      ->getStorage()
      ->loadByProperties([
        'type' => 'project',
        'status' => 1,
        'field_apartments' => $node->id(),
      ]
    );

    $stack = [];

    if ($node->hasField('field_floorplan')) {
      $stack = array_merge($stack, $node->field_floorplan->getValue());
    }

    $stack = array_merge($stack, $items->getValue());

    if ($parent_node_results) {
      $parent_node_nid = key($parent_node_results);
      $parent_node = Node::load($parent_node_nid);

      $stack = array_merge($stack, $parent_node->field_shared_apartment_images->getValue());
    }

    if ($stack) {
      foreach ($stack as $index => $item) {
        $item['_attributes'] = $item['_attributes'] ?? [];
        $item['_loaded'] = $item['_loaded'] ?? TRUE;
        $items->set($index, $item);
      }
    }

    return parent::viewElements($items, $langcode);
  }

}
