<?php

namespace Drupal\asu_csv_import\ImportTypes;

class TextType extends ImportType {

  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct($text)
  {
    if($this->isAllowedValue($text)){
      $this->value = $text;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() : string
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
   * Text is valid.
   *
   * @param $text
   * @return bool
   */
  private function isAllowedValue($text): bool
  {
    return true;
  }

}
