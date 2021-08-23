<?php

namespace Drupal\asu_api\Api\ElasticSearchApi;

use Drupal\asu_api\Api\ElasticSearchApi\Service\ApartmentService;
use Drupal\asu_api\Api\RequestHandler;
use Drupal\Core\Site\Settings;

/**
 * ElasticSearch api.
 */
class ElasticSearchApi {
  /**
   * Apartment service.
   *
   * @var \Drupal\asu_api\Api\ElasticSearchApi\Service\ApartmentService
   */
  private ApartmentService $apartmentService;

  /**
   * Api username.
   *
   * @var string|mixed
   */
  private ?string $username;

  /**
   * Api password.
   *
   * @var string|mixed
   */
  private ?string $password;

  /**
   * Constructor.
   *
   * ElasticSearchApi constructor.
   *
   * @param string $baseurl
   *   Elasticsearch baseurl.
   */
  public function __construct(string $baseurlVariable, string $usernameVariable, string $passwordVariable) {
    $baseurl = Settings::get($baseurlVariable);
    $this->username = Settings::get($usernameVariable);
    $this->password = Settings::get($passwordVariable);
    $handler = new RequestHandler($baseurl, ['auth' => [$this->username, $this->password]]);
    $this->apartmentService = new ApartmentService($handler);
  }

  /**
   * Get apartment service.
   *
   * @return \Drupal\asu_api\Api\ElasticSearchApi\Service\ApartmentService
   *   Apartment service.
   */
  public function getApartmentService(): ApartmentService {
    return $this->apartmentService;
  }

}
