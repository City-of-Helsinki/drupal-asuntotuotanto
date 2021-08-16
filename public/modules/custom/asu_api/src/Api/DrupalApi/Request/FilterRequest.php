<?php

namespace Drupal\asu_api\Api\DrupalApi\Request;

use Drupal\asu_api\Api\Request;

/**
 * Gets filters for apartment search.
 */
class FilterRequest extends Request {

  protected const METHOD = 'GET';
  protected const PATH = '/filters';

  /**
   * Language for the filters.
   *
   * @var mixed|string
   */
  private $language;

  /**
   * Constructor.
   *
   * @param string $language
   *   Language code for the filter request.
   */
  public function __construct($language = 'fi') {
    $this->language = $language;
  }

  /**
   * {@inheritDoc}
   */
  public function getPath(): string {
    if (!static::PATH) {
      throw new \LogicException('Missing path.');
    }
    return $this->language . static::PATH;
  }

  /**
   * {@inheritDoc}
   */
  public function toArray(): array {
    return [];
  }

  /**
   * Create.
   */
  public static function create($language): self {
    return new self($language);
  }

}
