<?php

namespace Drupal\asu_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\slick\Plugin\Field\FieldFormatter\SlickImageFormatter;

/**
 * Formatter used for project/apartment image field's combining.
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
    $stack = [];

    if ($node->bundle() == 'apartment') {
      $project = $node->getProject();
      // Floorplan must be first in apartment.
      if ($node->hasField('field_floorplan')) {
        $stack = array_merge($stack, $node->field_floorplan->getValue());
      }
      $stack = array_merge($stack, $items->getValue());
      $stack = array_merge($stack, $project->field_shared_apartment_images->getValue());
    }

    if ($node->bundle() == 'project') {
      $project = $node;
      $stack = array_merge($stack, $project->field_main_image->getValue());
      $stack = array_merge($stack, $items->getValue());
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
