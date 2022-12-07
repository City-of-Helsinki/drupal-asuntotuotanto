<?php

namespace Drupal\asu_application\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the apartment field widget.
 *
 * @FieldWidget(
 *   id = "asu_apartment_widget",
 *   label = @Translation("Asu apartment - Widget"),
 *   description = @Translation("Asu apartment - Widget"),
 *   field_types = {
 *     "asu_apartment"
 *   },
 * )
 */
class ApartmentWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['id'] = [
      '#type' => 'select',
      '#cardinality' => -1,
      '#title' => $this->t('Apartment'),
      '#required' => TRUE,
      '#empty_option' => [0 => $this->t('Select apartment')],
      // Apartment_values is set where ever the form is built.
      '#options' => $form['#apartment_values'] ?? [],
      '#default_value' => $items->getValue()[$delta]['id'] ?? 0,
      '#limit_validation_errors' => [],
      /*
      '#ajax' => [
        'wrapper' => 'edit-apartment-wrapper',
        'event' => 'change',
        'callback' => '::saveApplicationCallback',
      ],
      */
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $values = parent::massageFormValues($values, $form, $form_state);
    foreach ($values as $key => $value) {
      if (!isset($value['id']) || $value['id'] == 0) {
        continue;
      }
      $values[$key]['information'] = $form['apartment']['widget'][$key]['id']['#options'][$value['id']];
    }
    return $values;
  }

}
