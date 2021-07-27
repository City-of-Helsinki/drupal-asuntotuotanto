<?php

namespace Drupal\asu_csv_import\ImportTypes;

/**
 * Decimal type.
 */
class DecimalType extends ImportType {
  /**
   * {@inheritdoc}
   */
  protected $value;

  /**
   * DecimalType constructor.
   *
   * @param float $decimal
   *   Decimal.
   *
   * @throws \Exception
   */
  public function __construct($decimal) {
    if ($this->isAllowedValue($decimal)) {
      $this->value = $decimal;
    }
    else {
      throw new \Exception('DecimalType expects proper decimal value');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return (string) $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportValue() {
    return (string) $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportValue() {
    return (string) $this->value;
  }

  /**
   * Decimal is valid if it passes one of the three checks.
   *
   * @param float $decimal
   *   Decimal.
   *
   * @return bool
   *   Is allowed.
   */
  private function isAllowedValue($decimal): bool {
    if (empty($decimal)) {
      return TRUE;
    }
    else {
      if (!empty($decimal) && !is_numeric($decimal)) {
        return FALSE;
      }
      if (is_string($decimal) && is_numeric($decimal)) {
        return TRUE;
      }
      elseif (is_float($decimal)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
