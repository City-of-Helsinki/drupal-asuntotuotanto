<?php

namespace Drupal\asu_user;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Handles external data computing for custom field.
 */
class ExternalData extends FieldItemList {
  use ComputedItemListTrait;

  private Store $store;

  /**
   *
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->store = \Drupal::service('asu_user.tempstore');
  }

  /**
   * Compute the value.
   */
  protected function computeValue() {
    $delta = 0;
    $value = $this->store->get($this->getName());
    $this->list[$delta] = $this->createItem($delta, $value);
  }

  /**
   *
   */
  public function getValue() {
    $this->ensureComputedValue();
    return parent::getValue();
  }

}
