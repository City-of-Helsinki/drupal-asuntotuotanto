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
      '#required' => FALSE,
      '#empty_option' => [0 => $this->t('Select apartment')],
      // Apartment_values is set where ever the form is built.
      '#options' => isset($form['#apartment_values']) ? $form['#apartment_values'] : [],
      '#default_value' => isset($items->getValue()[$delta]['id']) ? $items->getValue()[$delta]['id'] : 0,
      '#ajax' => [
        'wrapper' => 'edit-apartment-wrapper',
        'event' => 'change',
        'callback' => '::saveApplicationCallback',
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $values = parent::massageFormValues($values, $form, $form_state);
    foreach ($values as $key => $value) {
      $values[$key]['information'] = $form['apartment']['widget'][$key]['id']['#options'][$value['id']];
    }
    return $values;
  }

}
