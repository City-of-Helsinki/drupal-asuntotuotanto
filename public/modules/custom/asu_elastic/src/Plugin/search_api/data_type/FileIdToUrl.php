<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\file\Entity\File;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * File id to url.
 *
 * @SearchApiDataType(
 *   id = "asu_file_url",
 *   label = @Translation("File id to url"),
 *   description = @Translation("Turns file id to url"),
 *   fallback_type = "string",
 * )
 */
class FileIdToUrl extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    if (is_array($value)) {
      $images = [];
      foreach ($value as $val) {
        if ($url = $this->getFileUrl($val)) {
          $images[] = $url;
        }
      }
      return $images;
    }

    return $this->getFileUrl($value);
  }

  /**
   * Get file by id.
   */
  private function getFileUrl($value) {
    if ($file = File::load((int) $value)) {
      $host = \Drupal::request()->getHost();
      return $host . $file->createFileUrl();
    }
    return $value;
  }

}
