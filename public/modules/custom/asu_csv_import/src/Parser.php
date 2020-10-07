<?php

namespace Drupal\asu_csv_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parser manager.
 */
class Parser {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs Parser object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Add content.
   *
   * @param mixed $content
   *   CSV content.
   *
   * @return array
   *   Prepared data.
   */
  public function data(Array $csv) {
    $return = [];

    if (!$csv || !is_array($csv)) {
      return;
    }

    $headers = $csv[0];
    unset($csv[0]);

    foreach ($csv as $index => $data) {
      foreach ($data as $key => $content) {
        if (isset($headers[$key])) {
          $content = Unicode::convertToUtf8($content, mb_detect_encoding($content));
          $fields = explode('|', $headers[$key]);

          $field = $fields[0];
          if (count($fields) > 1) {
            foreach ($fields as $key => $in) {
              $return['content'][$index][$field][$in] = $content;
            }
          }
          elseif (isset($return['content'][$index][$field])) {
            $prev = $return['content'][$index][$field];
            $return['content'][$index][$field] = [];

            if (is_array($prev)) {
              $prev[] = $content;
              $return['content'][$index][$field] = $prev;
            }
            else {
              $return['content'][$index][$field][] = $prev;
              $return['content'][$index][$field][] = $content;
            }
          }
          else {
            $return['content'][$index][current($fields)] = $content;
          }
        }
      }

      if (isset($return[$index])) {
        $return['content'][$index] = array_intersect_key($return[$index], array_flip($this->configuration['fields']));
      }
    }

    return $return;
  }

}
