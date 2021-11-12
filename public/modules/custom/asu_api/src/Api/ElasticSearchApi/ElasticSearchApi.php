<?php

namespace Drupal\asu_api\Api\ElasticSearchApi;

use Drupal\asu_api\Api\ElasticSearchApi\Service\ApartmentService;

/**
 * ElasticSearch api.
 */
class ElasticSearchApi {

  /**
   * Constructor.
   *
   * ElasticSearchApi constructor.
   *
   * @param string $baseurl
   *   Base url.
   * @param string $username
   *   Username.
   * @param string $password
   *   Password.
   */
  public function __construct() {
  }

  /**
   * Get apartment service.
   *
   * @return \Drupal\asu_api\Api\ElasticSearchApi\Service\ApartmentService
   *   Apartment service.
   */
  public function getApartmentService(): ApartmentService {
  }

}
