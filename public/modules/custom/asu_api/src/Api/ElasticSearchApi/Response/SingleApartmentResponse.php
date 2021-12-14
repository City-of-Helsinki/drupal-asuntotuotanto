<?php

namespace Drupal\asu_api\Api\ElasticSearchApi\Response;

use Drupal\asu_api\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for application request.
 */
class SingleApartmentResponse {

  /**
   * Apartment array.
   *
   * @var array
   */
  private array $apartment;

  /**
   * ApartmentResponse constructor.
   *
   * @param array $content
   *   Content from http request.
   *
   * @throws \Exception
   */
  public function __construct(array $content) {
    if (empty($content)) {
      throw new \Exception('No apartments found.');
    }
    $this->apartment = $content;
  }

  /**
   * Get apartment.
   *
   * @return array
   *   Apartments.
   */
  public function getApartment(): array {
    return $this->apartment;
  }

  /**
   * Create an ApplicationResponse from http response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   HttpResponse.
   *
   * @return SingleApartmentResponse
   *   Apartment response.
   *
   * @throws \Exception
   *    Apartments not found.
   */
  public static function createFromHttpResponse(ResponseInterface $response): SingleApartmentResponse {
    $responseContent = json_decode($response->getBody()->getContents(), TRUE);
    $content = $responseContent['hits']['hits'];
    if (empty($content)) {
      throw new RequestException('No apartments found.');
    }
    return new self($content[0]['_source']);
  }

}
