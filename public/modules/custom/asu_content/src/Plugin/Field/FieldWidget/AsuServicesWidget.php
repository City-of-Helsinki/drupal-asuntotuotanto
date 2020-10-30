<?php

namespace Drupal\asu_tasklist\Plugin\Field\FieldWidget;

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
class AsuTaskListWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $selected_vocabylary_id = $items[$delta]->getFieldDefinition()->getSettings()['selected_taxonomy_id'];
    $vocabulary = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_vocabulary', $selected_vocabylary_id);
    $term_list = [];

    if (isset($vocabulary)) {
      /** @var \Drupal\taxonomy\Entity\Term[] $terms */
      $terms = \Drupal::service('entity_type.manager')->getStorage("taxonomy_term")->loadTree($vocabulary->get('originalId'), 0, 1, TRUE);
      foreach ($terms as $key => $term) {
        $term_list[$term->id()] = $term->getName();
      }
    }

    $elements['service'] = [
      '#type' => 'select',
      '#options' => $term_list,
      '#title' => $this->t('Service'),
    ];

    $elements['distance'] = [
      '#type' => 'integer',
      '#default_value' => 0
    ];

    $elements = [];
    foreach ($term_list as $id => $name) {
      $bool = FALSE;
      $description = '';

      if (isset($task_list_values[$id])) {
        $bool = $task_list_values[$id]['value'];
        $description = $task_list_values[$id]['description'];
      }

      $elements["task:$id"] = [
        '#type' => 'checkbox',
        '#title' => $this->t($name),
        '#default_value' => $bool,
      ];

      $elements["description:$id"] = [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#title_display' => 'invisible',
        '#placeholder' => $this->t('Description'),
        '#default_value' => $description,
        '#maxlength' => 255,
      ];
    }

    $element += $elements;
    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $data = [];
    foreach ($values[0]['value'] as $key => $value) {
      $val = explode(':', $key);
      if ($val[0] == 'task') {
        $data[$val[1]]['tid'] = $val[1];
        $data[$val[1]]['value'] = $value;
      }
      if ($val[0] == 'description') {
        $data[$val[1]]['description'] = $value;
      }
    }

    return ['value' => serialize($data)];
  }

}
