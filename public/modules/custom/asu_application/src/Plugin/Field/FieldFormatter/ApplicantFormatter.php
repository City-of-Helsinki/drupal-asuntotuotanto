<?php

namespace Drupal\asu_application\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the applicant formatter.
 *
 * @FieldFormatter(
 *   id = "asu_applicant_formatter",
 *   label = @Translation("Applicant formatter"),
 *   field_types = {
 *     "asu_applicant"
 *   },
 * )
 */
class ApplicantFormatter extends FormatterBase {

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
      $element[$delta] = ['#markup' => "{$item->first_name}, {$item->email}<br>"];
    }

    return $element;
  }

}
