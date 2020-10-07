<?php

namespace Drupal\asu_csv_import\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * Plugin implementation for csv file upload field.
 *
 * @FieldType(
 *   id = "asu_csv_import",
 *   label = @Translation("ASU - Csv import"),
 *   category = @Translation("Reference"),
 *   default_widget = "file_generic",
 *   default_formatter = "file_default",
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}}
 * )
 */
class AsuCsvImport extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return parent::schema($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return parent::propertyDefinitions($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return parent::isEmpty();
  }

}
