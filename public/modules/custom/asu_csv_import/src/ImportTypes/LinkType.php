<?php

namespace Drupal\asu_csv_import\ImportTypes;

/**
 * Link type.
 */
class LinkType extends ImportType {
  /**
   * {@inheritdoc}
   */
  protected $value;

  /**
   * LinkType constructor.
   *
   * @param string $url
   *   Url.
   *
   * @throws \Exception
   */
  public function __construct($url) {
    if ($this->isAllowedValue($url)) {
      $this->value = $url;
    }
    else {
      throw new \Exception('Url field requires proper url: for example https://www.google.fi');
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
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportValue() {
    return $this->value;
  }

  /**
   * Is url.
   *
   * @param string $url
   *   Url.
   *
   * @return bool
   *   Is allowed.
   */
  private function isAllowedValue($url): bool {
    if (empty($url)) {
      return TRUE;
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
