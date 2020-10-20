<?php

namespace Drupal\asu_csv_import\ImportTypes;

/**
 * Text type.
 */
class TextType extends ImportType {
  /**
   * {@inheritdoc}
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct($text) {
    if ($this->isAllowedValue($text)) {
      $this->value = $text;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() : string {
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
   * Text is valid.
   *
   * @param string $text
   *   Text.
   *
   * @return bool
   *   Is Valid.
   */
  private function isAllowedValue($text): bool {
    return TRUE;
  }

}
