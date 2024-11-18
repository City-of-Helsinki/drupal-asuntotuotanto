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

  /**
   * User store.
   *
   * @var \Drupal\asu_user\Customer
   */
  private Customer $customer;

  /**
   * Construct.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   DataDefinitionInterface.
   * @param string|null $name
   *   Name.
   * @param \Drupal\Core\TypedData\TypedDataInterface|null $parent
   *   TypedDataInterface.
   */
  public function __construct(
    DataDefinitionInterface $definition,
    $name = NULL,
    ?TypedDataInterface $parent = NULL,
  ) {
    parent::__construct($definition, $name, $parent);
    $this->customer = \Drupal::service('asu_user.customer');
  }

  /**
   * {@inheritDoc}
   */
  protected function computeValue() {
    $delta = 0;
    $value = $this->customer->getUserField($this->getName());
    $this->list[$delta] = $this->createItem($delta, $value);
  }

  /**
   * {@inheritDoc}
   */
  public function getValue() {
    $this->ensureComputedValue();
    return parent::getValue();
  }

}
