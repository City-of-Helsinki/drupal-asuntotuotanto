<?php

namespace Drupal\asu_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

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

    $template = 'asdasd';

    $element = [
      '#type' => 'inline_template',
      '#template' => $template,
    ];
    return $element;
  }

}
