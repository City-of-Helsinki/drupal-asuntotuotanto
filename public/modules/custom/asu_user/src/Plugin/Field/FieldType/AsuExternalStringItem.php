<?php

namespace Drupal\asu_user\Plugin\Field\FieldType;

use Drupal\asu_user\Store;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Variant of the 'link' field that links to the current company.
 *
 * @FieldType(
 *   id = "asu_external_string",
 *   label = @Translation("External string"),
 *   description = @Translation("External string as a field value."),
 *   default_widget = "string_textfield",
 *   default_formatter = "string",
 * )
 */
class AsuExternalStringItem extends StringItem {
  /**
   * Whether or not the value has been calculated.
   *
   * @var bool
   */
  protected $isCalculated = FALSE;

  /**
   * Private tempstore.
   *
   * @var \Drupal\asu_user\Store
   */
  private Store $store;

  /**
   * Constructor.
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->store = \Drupal::service('asu_user.tempstore');
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    $this->ensureCalculated();
    return parent::__get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $this->ensureCalculated();
    return parent::isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->ensureCalculated();
    return parent::getValue();
  }

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      if (!$entity->isNew()) {
        $name = $this->getFieldDefinition()->getFieldStorageDefinition()->getName();
        $dataMap = \Drupal::config('asu_user.external_user_fields')->get('external_data_map');
        $value = [
          'value' => $this->store->get($name),
        ];
        $this->setValue($value);
      }
      $this->isCalculated = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [];
  }

}
