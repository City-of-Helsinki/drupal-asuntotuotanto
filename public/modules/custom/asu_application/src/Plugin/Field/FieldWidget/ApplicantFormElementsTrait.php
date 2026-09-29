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

    $fields = [
      'first_name' => [
        'type' => 'textfield',
        'title' => $this->t('First name'),
        'stored' => 'first_name',
        'profile' => 'first_name',
        'maxlength' => 50,
        'size' => 100,
      ],
      'last_name' => [
        'type' => 'textfield',
        'title' => $this->t('Last name'),
        'stored' => 'last_name',
        'profile' => 'last_name',
        'maxlength' => 50,
        'size' => 100,
      ],
      'date_of_birth' => [
        'type' => 'date',
        'title' => $this->t('Date of birth'),
        'stored' => 'date_of_birth',
        'profile' => 'date_of_birth',
        'size' => 30,
      ],
      'personal_id' => [
        'type' => 'textfield',
        'title' => $this->t('Personal id'),
        'description' => $this->t('last 4 characters'),
        'minlength' => $personalIdLength,
        'maxlength' => $personalIdLength,
        'default' => $this->personalIdDefault($storedValues, $emptyDefault),
      ],
      'address' => [
        'type' => 'textfield',
        'title' => $this->t('Street address'),
        'stored' => 'address',
        'profile' => 'street_address',
        'maxlength' => 99,
      ],
      'postal_code' => [
        'type' => 'textfield',
        'title' => $this->t('Postal code'),
        'stored' => 'postal_code',
        'profile' => 'postal_code',
        'maxlength' => 5,
        'size' => 50,
        'minlength' => $required ? 5 : NULL,
      ],
      'city' => [
        'type' => 'textfield',
        'title' => $this->t('City'),
        'stored' => 'city',
        'profile' => 'city',
        'maxlength' => 50,
        'size' => 50,
      ],
      'phone' => [
        'type' => 'textfield',
        'title' => $this->t('Phone number'),
        'stored' => 'phone',
        'profile' => 'phone_number',
        'maxlength' => 20,
        'size' => 20,
      ],
      'email' => [
        'type' => 'email',
        'title' => $this->t('Email'),
        'stored' => 'email',
        'profile' => 'email',
        'maxlength' => 99,
        'size' => 50,
      ],
    ];

    foreach ($fields as $name => $definition) {
      $field = [
        '#type' => $definition['type'],
        '#title' => $definition['title'],
        '#required' => $required,
      ];
      if (array_key_exists('default', $definition)) {
        $field['#default_value'] = $definition['default'];
      }
      else {
        $field['#default_value'] = $this->coalesceApplicantDefault(
          $storedValues[$definition['stored']] ?? NULL,
          $userInformation[$definition['profile']] ?? NULL,
          $emptyDefault
        );
      }
      foreach (['description', 'maxlength', 'minlength', 'size'] as $property) {
        if (isset($definition[$property]) && $definition[$property] !== NULL) {
          $field['#' . $property] = $definition[$property];
        }
      }
      $element[$name] = $field;
    }

    return $element;
  }

  /**
   * Default value for the personal id (last 4 characters) field.
   *
   * @param array $storedValues
   *   Values already stored on the field item.
   * @param mixed $emptyDefault
   *   Fallback when personal id is not stored.
   *
   * @return mixed
   *   Personal id default for the form element.
   */
  protected function personalIdDefault(array $storedValues, $emptyDefault) {
    if (!empty($storedValues['personal_id'])) {
      return substr($storedValues['personal_id'], -4);
    }
    return $emptyDefault ?? '';
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
