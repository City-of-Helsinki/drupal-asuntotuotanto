<?php

namespace Drupal\asu_content\Plugin\Field\FieldFormatter;

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

    if ($node->hasField('field_floorplan')) {
      $merged = array_merge($node->field_floorplan->getValue(), $items->getValue());

      foreach ($merged as $index => $item) {
        $items->set($index, $item);
      }
    }

    return parent::viewElements($items, $langcode);
  }

}
