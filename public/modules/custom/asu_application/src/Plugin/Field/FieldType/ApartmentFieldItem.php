<?php

namespace Drupal\asu_application\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of apartment.
 *
 * @FieldType(
 *   id = "asu_apartment",
 *   label = @Translation("Apartment"),
 *   description = @Translation("Selected apartments"),
 *   default_formatter = "asu_apartment_formatter",
 *   default_widget = "asu_apartment_widget",
 * )
 */
class ApartmentFieldItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'id' => [
          'type' => 'varchar',
          'length' => 255,
          'default' => '',
          // 'not_null' => FALSE,
        ],
        'information' => [
          'type' => 'varchar',
          'length' => 255,
          'default' => '',
          // 'not_null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['id'] = DataDefinition::create('string')
      ->setLabel(t('id'));
    $properties['information'] = DataDefinition::create('string')
      ->setLabel(t('information'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->id === NULL || $this->id === '';
  }

}
