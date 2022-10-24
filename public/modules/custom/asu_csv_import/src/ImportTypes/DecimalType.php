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
    $value = str_replace(',', '.', $decimal);
    if ($this->isAllowedValue($value)) {
      $this->value = $value;
    }
    else {
      throw new \Exception('Given value is not proper decimal');
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
   * @param string $input
   *   Decimal.
   *
   * @return bool
   *   Is allowed.
   */
  private function isAllowedValue(string $input): bool {
    if (empty($input)) {
      return TRUE;
    }
    else {
      $input = str_replace(' ', '', $input);
      if (!empty($input) && !is_numeric($input)) {
        return FALSE;
      }
      if (is_string($input) && is_numeric($input)) {
        return TRUE;
      }
      elseif (is_float($input)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
