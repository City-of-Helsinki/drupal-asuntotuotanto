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
      throw new \Exception('Value is not proper decimal.');
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
    return floatval($this->value);
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
  private function isAllowedValue($input): bool {
    if (empty($input)) {
      return TRUE;
    }
    else {
      $number = floatval(str_replace(',', '.', $input));
      if (!empty($number) && !is_numeric($number)) {
        return FALSE;
      }
      if (is_string($number) && is_numeric($number)) {
        return TRUE;
      }
      elseif (is_float($number)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
