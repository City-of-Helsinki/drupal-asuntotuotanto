<?php

namespace Drupal\asu_api\Api\DrupalApi\Request;

use Drupal\asu_api\Api\Request;

/**
 * Application request.
 */
class ApartmentRequest extends Request {
  /**
   * Api path.
   *
   * @var string
   */
  protected const PATH = '/content';

  /**
   * Method.
   *
   * @var string
   */
  protected const METHOD = 'GET';


  private int $contentId;

  /**
   * Constructor.
   *
   * @param int $contentId
   */
  public function __construct(int $contentId) {
    $this->contentId = $contentId;
  }

  /**
   * Get path.
   *
   * @return string
   *   Request path.
   */
  public function getPath(): string {
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $languageCode = $language->getId();
    $path = parent::getPath();
    return "$languageCode/$path/{$this->contentId}";
  }

}
