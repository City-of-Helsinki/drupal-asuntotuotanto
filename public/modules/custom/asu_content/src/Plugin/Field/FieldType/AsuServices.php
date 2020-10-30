<?php

namespace Drupal\asu_content\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides Services fieldtype.
 *
 * @FieldType(
 *   id = "asu_services",
 *   label = @Translation("Services"),
 *   default_formatter = "asu_service_formatter",
 *   default_widget = "asu_service_widget",
 * )
 */
class AsuServices extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
        'selected_taxonomy_id' => 'taxonomy_id',
      ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $vocabularies = Vocabulary::loadMultiple();
    $vocabularies_list = [];
    foreach ($vocabularies as $vid => $vocabulary) {
      $vocabularies_list[$vocabulary->uuid()] = $vocabulary->get('name');
    }

    $elements['selected_taxonomy_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select vocabulary'),
      '#options' => $vocabularies_list,
      '#default_value' => $this->getSetting('selected_taxonomy_id'),
      '#required' => TRUE,
      '#description' => $this->t('Vocabulary which will be used'),
      '#disabled' => $has_data,
    ];
    return $elements + parent::storageSettingsForm($form, $form_state, $has_data);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'term_id' => [
          'type' => 'int',
          'not_null' => TRUE,
        ],
        'distance' => [
          'type' => 'int',
          'not_null' => TRUE,
        ],
      ],
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $term = $this->get('term_id')->getValue();
    $distance = $this->get('term_id')->getValue();
    return ($term === NULL || $term === '') || ($distance === NULL || $distance === '');
  }

}
