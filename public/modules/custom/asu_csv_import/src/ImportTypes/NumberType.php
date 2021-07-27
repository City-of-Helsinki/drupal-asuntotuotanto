<?php

namespace Drupal\asu_csv_import\ImportTypes;

/**
 * Number type.
 */
class NumberType extends ImportType {
  /**
   * {@inheritdoc}
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct($number) {
    if ($this->isAllowedValue($number)) {
      $this->value = $number ? $number : 0;
    }
    else {
      throw new \Exception('NumberType expects proper numeric value');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return (int) $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportValue() {
    return (int) $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportValue() {
    return (int) $this->value;
  }

  /**
   * Number needs to be numeric or empty.
   *
   * @param int $number
   *   Number.
   *
   * @return bool
   *   Is allowed.
   */
  private function isAllowedValue($number): bool {
    return is_numeric($number) || empty($number);
  }

}
