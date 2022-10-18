<?php

namespace Drupal\asu_application\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the main applicant field widget.
 *
 * @FieldWidget(
 *   id = "asu_main_applicant_widget",
 *   label = @Translation("Asu main applicant - Widget"),
 *   description = @Translation("Asu main applicant - Widget"),
 *   field_types = {
 *     "asu_main_applicant"
 *   },
 * )
 */
class MainApplicantWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#maxlength' => 50,
      '#size' => 100,
      '#default_value' => $items->getValue()[$delta]['first_name'] ?? '',
      '#required' => TRUE,
    ];

    $element['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#maxlength' => 50,
      '#size' => 100,
      '#default_value' => $items->getValue()[$delta]['last_name'] ?? '',
      '#required' => TRUE,
    ];

    $element['date_of_birth'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of birth'),
      '#size' => 30,
      '#default_value' => $items->getValue()[$delta]['date_of_birth'] ?? '',
      '#required' => TRUE,
    ];

    $element['personal_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Personal id'),
      '#description' => $this->t('last 5 characters'),
      '#minlength' => 5,
      '#maxlength' => 5,
      '#default_value' => $items->getValue()[$delta]['personal_id'] ?? '',
      '#required' => TRUE,
    ];

    $element['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street address'),
      '#maxlength' => 99,
      '#default_value' => $items->getValue()[$delta]['address'] ?? '',
      '#required' => TRUE,
    ];

    $element['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal code'),
      '#maxlength' => 5,
      '#size' => 50,
      '#default_value' => $items->getValue()[$delta]['postal_code'] ?? '',
      '#required' => TRUE,
    ];

    $element['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#maxlength' => 50,
      '#size' => 50,
      '#default_value' => $items->getValue()[$delta]['city'] ?? '',
      '#required' => TRUE,
    ];

    $element['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number'),
      '#maxlength' => 20,
      '#size' => 20,
      '#default_value' => $items->getValue()[$delta]['phone'] ?? '',
      '#required' => TRUE,
    ];

    $element['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#maxlength' => 99,
      '#size' => 50,
      '#default_value' => $items->getValue()[$delta]['email'] ?? '',
      '#required' => TRUE,
    ];

    return $element;
  }

}
