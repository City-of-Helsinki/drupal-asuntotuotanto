<?php

namespace Drupal\asu_api\Api\ElasticSearchApi\Request;

use Drupal\asu_api\Api\Request;

/**
 * Application request.
 */
class SingleApartmentRequest extends Request {

  protected const METHOD = 'POST';
  protected const PATH = '/_search';

  /**
   * Apartment id.
   *
   * @var string
   */
  private String $apartmentId;

  /**
   * Constructor.
   */
  public function __construct(string $apartmentId) {
    $this->apartmentId = $apartmentId;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      // "size" => 10000,
      "query" => [
        "match" => [
          "nid" => (int) $this->apartmentId,
        ],
      ],
    ];
  }

}
