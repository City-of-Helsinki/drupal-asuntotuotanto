<?php

namespace Drupal\asu_application\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the apartment formatter.
 *
 * @FieldFormatter(
 *   id = "asu_apartment_formatter",
 *   label = @Translation("apartment formatter"),
 *   field_types = {
 *     "asu_apartment"
 *   },
 * )
 */
class ApartmentFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      // Render each element as markup.
      $element[$delta] = ['#markup' => "{$item->id}: {$item->information}<br>"];
    }

    return $element;
  }

}
