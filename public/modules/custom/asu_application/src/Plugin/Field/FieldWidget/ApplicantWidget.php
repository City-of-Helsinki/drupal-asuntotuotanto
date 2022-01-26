<?php

namespace Drupal\asu_application\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the applicant field widget.
 *
 * @FieldWidget(
 *   id = "asu_applicant_widget",
 *   label = @Translation("Asu applicant - Widget"),
 *   description = @Translation("Asu applicant - Widget"),
 *   field_types = {
 *     "asu_applicant"
 *   },
 * )
 */
class ApplicantWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'asu_application/additional-applicant';

    $element['has_additional_applicant'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add additional applicant'),
      '#default_value' => !$items->isEmpty(),
    ];

    $element['applicant_prefix'] = [
      '#type' => 'markup',
      '#markup' => '<div id="applicant-wrapper" class="application-form__applicant-form">',
    ];

    $element['application_information_prefix'] = [
      '#type' => 'markup',
      '#markup' => '<div class="application-form__application-information">',
    ];

    $element['application_information_tooltip'] = [
      '#type' => 'markup',
      '#markup' => '<p class="application-form__application-information-tooltip">' . $this->t('
      This applicant cannot complete another application for the same item.') . '</p>',
    ];

    $element['application_information_suffix'] = [
      '#type' => 'markup',
      '#markup' => '</div>',
    ];

    $element['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#maxlength' => 50,
      '#size' => 100,
      '#default_value' => isset($items->getValue()[$delta]['first_name']) ? $items->getValue()[$delta]['first_name'] : '',
    ];

    $element['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#maxlength' => 50,
      '#size' => 100,
      '#default_value' => isset($items->getValue()[$delta]['last_name']) ? $items->getValue()[$delta]['last_name'] : '',
    ];

    $element['date_of_birth'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of birth'),
      '#size' => 30,
      '#default_value' => isset($items->getValue()[$delta]['date_of_birth']) ? $items->getValue()[$delta]['date_of_birth'] : '',
    ];

    $element['personal_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Personal id'),
      '#description' => $this->t('last 5 characters'),
      '#minlength' => 5,
      '#maxlength' => 5,
      '#default_value' => isset($items->getValue()[$delta]['personal_id']) ? $items->getValue()[$delta]['personal_id'] : '',
    ];

    $element['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street address'),
      '#maxlength' => 99,
      '#default_value' => isset($items->getValue()[$delta]['address']) ? $items->getValue()[$delta]['address'] : '',
    ];

    $element['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal code'),
      '#maxlength' => 5,
      '#size' => 50,
      '#default_value' => isset($items->getValue()[$delta]['postal_code']) ? $items->getValue()[$delta]['postal_code'] : '',
    ];

    $element['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#maxlength' => 50,
      '#size' => 50,
      '#default_value' => isset($items->getValue()[$delta]['city']) ? $items->getValue()[$delta]['city'] : '',
    ];

    $element['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number'),
      '#maxlength' => 20,
      '#size' => 20,
      '#default_value' => isset($items->getValue()[$delta]['phone']) ? $items->getValue()[$delta]['phone'] : '',
    ];

    $element['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#maxlength' => 99,
      '#size' => 50,
      '#default_value' => isset($items->getValue()[$delta]['email']) ? $items->getValue()[$delta]['email'] : '',
    ];

    $element['applicant_suffix'] = [
      '#type' => 'markup',
      '#markup' => '</div>',
    ];

    return $element;
  }

}
