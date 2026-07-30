<?php

namespace Drupal\asu_application\Plugin\Field\FieldWidget;

/**
 * Shared applicant contact form elements for main/additional applicant widgets.
 */
trait ApplicantFormElementsTrait {

  /**
   * Empty backend/user profile defaults.
   *
   * @return array
   *   Null-filled profile keys used by applicant widgets.
   */
  protected function emptyUserInformation(): array {
    return [
      'first_name' => NULL,
      'last_name' => NULL,
      'date_of_birth' => NULL,
      'street_address' => NULL,
      'postal_code' => NULL,
      'city' => NULL,
      'phone_number' => NULL,
      'email' => NULL,
    ];
  }

  /**
   * Build shared applicant contact fields onto a form element.
   *
   * @param array $element
   *   Form element to extend.
   * @param array $storedValues
   *   Values already stored on the field item.
   * @param array $userInformation
   *   Fallback profile data from Backend API.
   * @param array $options
   *   Widget options:
   *   - required (bool): whether fields are required.
   *   - personal_id_length (int): min/max length for personal id.
   *   - empty_default (mixed): fallback when both stored and profile are empty.
   *
   * @return array
   *   Element with contact fields added.
   */
  protected function appendApplicantContactFields(
    array $element,
    array $storedValues,
    array $userInformation,
    array $options = [],
  ): array {
    $required = !empty($options['required']);
    $personalIdLength = (int) ($options['personal_id_length'] ?? 4);
    $emptyDefault = array_key_exists('empty_default', $options)
      ? $options['empty_default']
      : '';

    $default = function (string $storedKey, string $profileKey) use ($storedValues, $userInformation, $emptyDefault) {
      return $this->coalesceApplicantDefault(
        $storedValues[$storedKey] ?? NULL,
        $userInformation[$profileKey] ?? NULL,
        $emptyDefault
      );
    };

    $element['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#maxlength' => 50,
      '#size' => 100,
      '#default_value' => $default('first_name', 'first_name'),
      '#required' => $required,
    ];

    $element['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#maxlength' => 50,
      '#size' => 100,
      '#default_value' => $default('last_name', 'last_name'),
      '#required' => $required,
    ];

    $element['date_of_birth'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of birth'),
      '#size' => 30,
      '#default_value' => $default('date_of_birth', 'date_of_birth'),
      '#required' => $required,
    ];

    $personalIdDefault = !empty($storedValues['personal_id'])
      ? substr($storedValues['personal_id'], -4)
      : ($emptyDefault === NULL ? NULL : '');

    $element['personal_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Personal id'),
      '#description' => $this->t('last 4 characters'),
      '#minlength' => $personalIdLength,
      '#maxlength' => $personalIdLength,
      '#default_value' => $personalIdDefault ?? '',
      '#required' => $required,
    ];

    $element['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street address'),
      '#maxlength' => 99,
      '#default_value' => $default('address', 'street_address'),
      '#required' => $required,
    ];

    $postalCode = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal code'),
      '#maxlength' => 5,
      '#size' => 50,
      '#default_value' => $default('postal_code', 'postal_code'),
      '#required' => $required,
    ];
    if ($required) {
      $postalCode['#minlength'] = 5;
    }
    $element['postal_code'] = $postalCode;

    $element['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#maxlength' => 50,
      '#size' => 50,
      '#default_value' => $default('city', 'city'),
      '#required' => $required,
    ];

    $element['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number'),
      '#maxlength' => 20,
      '#size' => 20,
      '#default_value' => $default('phone', 'phone_number'),
      '#required' => $required,
    ];

    $element['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#maxlength' => 99,
      '#size' => 50,
      '#default_value' => $default('email', 'email'),
      '#required' => $required,
    ];

    return $element;
  }

  /**
   * Choose stored value, profile fallback, or empty default.
   *
   * @param mixed $stored
   *   Value from the field item.
   * @param mixed $profile
   *   Value from Backend profile.
   * @param mixed $emptyDefault
   *   Fallback when both are empty.
   *
   * @return mixed
   *   Resolved default value.
   */
  protected function coalesceApplicantDefault($stored, $profile, $emptyDefault) {
    if ($stored !== NULL && $stored !== '') {
      return $stored;
    }
    if ($profile !== NULL && $profile !== '') {
      return $profile;
    }
    return $emptyDefault;
  }

}
