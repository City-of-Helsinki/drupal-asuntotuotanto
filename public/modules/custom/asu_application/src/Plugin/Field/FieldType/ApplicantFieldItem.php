<?php

namespace Drupal\asu_application\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of applicant.
 *
 * @FieldType(
 *   id = "asu_applicant",
 *   label = @Translation("Applicant"),
 *   description = @Translation("Additional applicants for application"),
 *   default_formatter = "asu_applicant_formatter",
 *   default_widget = "asu_applicant_widget",
 * )
 */
class ApplicantFieldItem extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'first_name' => [
          'type' => 'varchar',
          'length' => 100,
          'default' => '',
        ],
        'last_name' => [
          'type' => 'varchar',
          'length' => 100,
          'default' => '',
        ],
        'date_of_birth' => [
          'type' => 'varchar',
          'length' => 30,
          'default' => '',
        ],
        'personal_id' => [
          'type' => 'varchar',
          'length' => 5,
          'default' => '',
        ],
        'address' => [
          'type' => 'varchar',
          'length' => 100,
          'default' => '',
        ],
        'postal_code' => [
          'type' => 'varchar',
          'length' => 10,
          'default' => '',
        ],
        'city' => [
          'type' => 'varchar',
          'length' => 100,
          'default' => '',
        ],
        'phone' => [
          'type' => 'varchar',
          'length' => 20,
          'default' => '',
        ],
        'email' => [
          'type' => 'varchar',
          'length' => 255,
          'default' => '',
        ],
      ],
    ];

  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['first_name'] = DataDefinition::create('string')
      ->setLabel(t('First name'));

    $properties['last_name'] = DataDefinition::create('string')
      ->setLabel(t('Last name'));

    $properties['date_of_birth'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('Date value'));

    $properties['personal_id'] = DataDefinition::create('string')
      ->setLabel(t('Personal id'));

    $properties['street_address'] = DataDefinition::create('string')
      ->setLabel(t('Street address'));

    $properties['postal_code'] = DataDefinition::create('string')
      ->setLabel(t('Postal code'));

    $properties['city'] = DataDefinition::create('string')
      ->setLabel(t('City'));

    $properties['phone'] = DataDefinition::create('string')
      ->setLabel(t('Phone number'));

    $properties['email'] = DataDefinition::create('email')
      ->setLabel(t('Email address'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->first_name === NULL ||
           $this->first_name === '' &&
           $this->last_name === NULL ||
           $this->last_name === '' &&
           $this->date_of_birth === NULL ||
           $this->date_of_birth === '' &&
           $this->personal_id === NULL ||
           $this->personal_id === '' &&
           $this->street_address === NULL ||
           $this->street_address === '' &&
           $this->city === NULL ||
           $this->city === '' &&
           $this->phone === NULL ||
           $this->phone === '' &&
           $this->email === NULL ||
           $this->email === '';
  }

}
