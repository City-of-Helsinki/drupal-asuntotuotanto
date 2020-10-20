<?php

namespace Drupal\asu_csv_import\ImportTypes;

/**
 * Boolean type.
 */
class BooleanType extends ImportType {
  /**
   * Allowed values for TRUE.
   */
  const ALLOWED_TRUE_VALUES = [
    1,
    '1',
    'kyllä',
    'k',
    'on',
    'yes',
    'y',
  ];

  /**
   * Allowed values for FALSE.
   */
  const ALLOWED_FALSE_VALUES = [
    '',
    0,
    '0',
    'ei',
    'e',
    'no',
    'n',
  ];

  /**
   * Value of this object.
   *
   * @var int
   */
  protected $value;

  /**
   * BooleanType constructor.
   *
   * @param bool $bool
   *   Boolean from csv.
   *
   * @throws \Exception
   */
  public function __construct($bool) {
    if ($this->isAllowedValue($bool)) {
      if ($this->isTrue($bool)) {
        $this->value = 1;
      }
      if ($this->isFalse($bool)) {
        $this->value = 0;
      }
    }
    else {
      throw new \Exception('BooleanType expects proper boolean value');
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
    return $this->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getExportValue() {
    return $this->value === 1 ? 'Kyllä' : 'Ei';
  }

  /**
   * Is.
   */
  private function isAllowedValue($bool): bool {
    return $this->isTrue($bool) || $this->isFalse($bool);
  }

  /**
   * Check if.
   */
  private function isTrue($bool) {
    return in_array($bool, self::ALLOWED_TRUE_VALUES);
  }

  /**
   * {@inheritdoc}
   */
  private function isFalse($bool) {
    return in_array($bool, self::ALLOWED_FALSE_VALUES);
  }

}
