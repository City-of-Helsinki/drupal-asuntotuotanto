<?php

namespace Drupal\asu_csv_import\ImportTypes;

class DecimalType extends ImportType {

  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct($decimal)
  {
    if($this->isAllowedValue($decimal)){
      $this->value = $decimal;
    } else {
      Throw new \Exception('DecimalType expects proper decimal value');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue()
  {
    return (string)$this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportValue()
  {
    return (string)$this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportValue()
  {
    return (string)$this->value;
  }

  /**
   * Decimal is valid if it passes one of the following checks.
   * Must be empty or.
   * Must be numeric string or.
   * Must pass is_float check.
   *
   * @param $decimal
   * @return bool
   */
  private function isAllowedValue($decimal): bool
  {
    if(empty($decimal)){
      return true;
    } else {
      if(!empty($decimal) && !is_numeric($decimal)) {
        return false;
      }
      if(is_string($decimal) && is_numeric($decimal)){
        return true;
      } else if(is_float($decimal)){
        return true;
      }
    }
    return false;
  }

}
