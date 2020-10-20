<?php

namespace Drupal\asu_csv_import\ImportTypes;

class DateType extends ImportType {

  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct($date)
  {
    if($this->isAllowedValue($date)){
      $this->value = $date;
    } else {
      throw new \Exception('Date is not valid type');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue()
  {
    return $this->value ? strtotime($this->value) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getImportValue(){
    return strtotime($this->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public function getExportValue(){
    if($this->value){
      return date('d.m.Y H:i', strtotime($this->value));
    }
    return '';
  }

  private function isAllowedValue($date): bool
  {
    if(empty($date)){
      return true;
    }
    try {
      $dt = new \DateTime($date);
      return true;
    }
    catch(\Exception $e){
      return false;
    }
  }
}
