<?php

namespace Drupal\asu_csv_import\ImportTypes;

/**
 * Date type.
 */
class DateType extends ImportType {

  /**
   * {@inheritdoc}
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct($date) {
    if ($this->isAllowedValue($date)) {
      $this->value = $date;
    }
    else {
      throw new \Exception('Date is not valid value');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportValue() {
    $d = new \DateTime($this->getValue());
    return $d->format('Y-m-d\TH:i:s');
  }

  /**
   * {@inheritdoc}
   */
  public function getExportValue() {
    if ($this->value) {
      return date('d.m.Y H:i', strtotime($this->value));
    }
    return '';
  }

  /**
   * Check if value is allowed.
   *
   * @param mixed $date
   *   Date.
   *
   * @return bool
   *   Is allowed value.
   */
  private function isAllowedValue($date): bool {
    if (empty($date)) {
      return TRUE;
    }
    try {
      new \DateTime($date);
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
