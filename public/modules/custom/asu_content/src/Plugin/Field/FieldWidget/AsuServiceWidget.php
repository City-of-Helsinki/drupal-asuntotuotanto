<?php

namespace Drupal\asu_content\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A widget bar.
 *
 * @FieldWidget(
 *   id = "asu_service_widget",
 *   label = @Translation("Services widget"),
 *   field_types = {
 *     "asu_services",
 *   }
 * )
 */
class AsuServiceWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $selected_vocabylary_id = $items[$delta]->getFieldDefinition()->getSettings()['selected_taxonomy_id'];
    $vocabulary = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_vocabulary', $selected_vocabylary_id);
    $term_list = [$this->t('Select service')];

    if (isset($vocabulary)) {
      /** @var \Drupal\taxonomy\Entity\Term[] $terms */
      $terms = \Drupal::service('entity_type.manager')->getStorage("taxonomy_term")->loadTree($vocabulary->get('originalId'), 0, 1, TRUE);
      foreach ($terms as $key => $term) {
        $term_list[$term->id()] = $term->getName();
      }
    }

    $term_id = $items[$delta]->term_id ?? 0;
    $distance = $items[$delta]->distance ?? 0;

    $elements['term_id'] = [
      '#type' => 'select',
      '#options' => $term_list,
      '#title' => $this->t('Service'),
      '#default_value' => $term_id,
    ];

    $elements['distance'] = [
      '#type' => 'number',
      '#title' => $this->t('Distance'),
      '#default_value' => $distance,
    ];

    $element += $elements;
    return $element;
    // Return ['value' => $element];.
  }

}
