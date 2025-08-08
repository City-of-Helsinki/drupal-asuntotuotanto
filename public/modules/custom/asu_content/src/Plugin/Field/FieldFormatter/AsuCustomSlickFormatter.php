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
      if ($project->hasField('field_shared_apartment_images') && !$project->get('field_shared_apartment_images')->isEmpty()) {
        $stack = array_merge($stack, $project->get('field_shared_apartment_images')->getValue());
      }
    }

    if ($node->bundle() == 'project') {
      $project = $node;
      $stack = array_merge($stack, $project->field_main_image->getValue());
      $stack = array_merge($stack, $items->getValue());
    }

    if ($stack) {
      $new_items = [];

      foreach ($stack as $index => $item) {
        $item['_attributes'] = $item['_attributes'] ?? [];

        $label = $node->label();
        $alt_text = $label . ', kuva ' . ($index + 1);

        $item['_attributes']['alt'] = $alt_text;
        $item['alt'] = $alt_text;

        $item['_loaded'] = $item['_loaded'] ?? TRUE;
        $new_items[$index] = $item;
      }

      $items->setValue($new_items);
    }

    return parent::viewElements($items, $langcode);
  }

}
