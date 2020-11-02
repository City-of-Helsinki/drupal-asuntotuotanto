<?php

namespace Drupal\asu_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\taxonomy\Entity\Term;

/**
 * Plugin implementation of the Tasklist formatter.
 *
 * @FieldFormatter(
 *   id = "asu_service_formatter",
 *   label = @Translation("Service formatter"),
 *   field_types = {
 *     "asu_services"
 *   }
 * )
 */
class AsuServiceFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach($items as $delta => $item) {
      if($term = Term::load($item->get('term_id')->getValue())){
        $term_name = $term->getName();
        $distance = $item->get('distance')->getValue();
        $element[$delta] = ['#markup' => "$term_name $distance m"];
      }
    }
    return $element;
  }

}
