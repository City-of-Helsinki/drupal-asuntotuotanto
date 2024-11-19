<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * File id to url.
 *
 * @SearchApiDataType(
 *   id = "asu_file_url",
 *   label = @Translation("File id to url"),
 *   description = @Translation("Turns file id to url"),
 *   default = "true",
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
      foreach ($value as $file) {
        if ($url = $this->getFileUrl($file)) {
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
      /** @var \Drupal\file\Validation\FileValidatorInterface $file_validator */
      $file_validator = \Drupal::service('file.validator');
      if (empty($file_validator->validate($file, ['extensions' => 'png jpg jpeg']))) {
        $style = ImageStyle::load('original_m');
        return $style->buildUrl($file->getFileUri());
      }
      return $file->createFileUrl(FALSE);
    }
    return $value;
  }

}
