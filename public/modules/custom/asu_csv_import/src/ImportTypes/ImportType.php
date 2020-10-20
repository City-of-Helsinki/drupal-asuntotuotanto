<?php

namespace Drupal\asu_csv_import\ImportTypes;

/**
 *
 */
abstract class ImportType {

  protected $value;

  /**
   * Constructor validates and sets the $value.
   * In case of invalid value throw an exception.
   *
   * @param Mixed $value
   *
   * @throws \Exception
   */
  abstract public function __construct($value);

  /**
   * Original value.
   *
   * @return mixed
   */
  abstract public function getValue();

  /**
   * Machine readable value for Drupal.
   *
   * @return mixed
   */
  abstract public function getImportValue();

  /**
   * Human readable value to write in csv export file.
   *
   * @return mixed
   */
  abstract public function getExportValue();

  /**
   * Tostring.
   *
   * @return string
   */
  public function __toString() {
    return (string) $this->value;
  }

}
