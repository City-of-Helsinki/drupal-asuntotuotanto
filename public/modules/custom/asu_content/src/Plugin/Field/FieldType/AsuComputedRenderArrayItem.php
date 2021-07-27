<?php

namespace Drupal\asu_content\Plugin\Field\FieldType;

use Drupal\computed_field_plugin\Plugin\Field\FieldType\ComputedRenderArrayItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Class ComputedRenderArrayItem.
 *
 * @FieldType(
 *   id = "asu_computed_render_array",
 *   label = @Translation("ASU - Computed render array"),
 *   default_formatter = "computed_render_array_formatter"
 * )
 */
class AsuComputedRenderArrayItem extends ComputedRenderArrayItem {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return '#markup';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['#markup'] = DataDefinition::create('any')
      ->setLabel(t('Render array value'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [];
  }

}
