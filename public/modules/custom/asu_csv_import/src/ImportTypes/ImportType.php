<?php

namespace Drupal\asu_csv_import\ImportTypes;

/**
 * Import type.
 */
abstract class ImportType {

  /**
   * Value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * Constructor validates and sets the $value.
   *
   * @param mixed $value
   *   Value.
   *
   * @throws \Exception
   */
  abstract public function __construct($value);

  /**
   * Original value.
   *
   * @return mixed
   *   Value.
   */
  abstract public function getValue();

  /**
   * Machine readable value for Drupal.
   *
   * @return mixed
   *   Value.
   */
  abstract public function getImportValue();

  /**
   * Human readable value to write in csv export file.
   *
   * @return mixed
   *   Value.
   */
  abstract public function getExportValue();

  /**
   * To string.
   *
   * @return string
   *   Value.
   */
  public function __toString() {
    return (string) $this->value;
  }

}
