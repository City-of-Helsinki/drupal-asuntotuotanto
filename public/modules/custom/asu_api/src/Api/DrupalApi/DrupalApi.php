<?php

namespace Drupal\asu_api\Api\DrupalApi;

use Drupal\asu_api\Api\DrupalApi\Service\ApartmentService;
use Drupal\asu_api\Api\DrupalApi\Service\ApplicationService;
use Drupal\asu_api\Api\DrupalApi\Service\FilterService;
use Drupal\asu_api\Api\RequestHandler;
use Drupal\Core\Site\Settings;

/**
 * Integration to drupal.
 */
class DrupalApi {

  /**
   * ApplicationService.
   *
   * @var \Drupal\asu_api\Api\DrupalApi\Service\ApplicationService
   *    ApplicationService.
   */
  private $applicationService;

  /**
   * FilterService.
   *
   * @var \Drupal\asu_api\Api\DrupalApi\Service\FilterService
   *    FilterService.
   */
  private $filtersService;

  private $apartmentService;

  /**
   * Constructor.
   *
   * @param string $apiUrl
   *   Api url.
   */
  public function __construct(string $apiUrlKey) {
    $apiUrl = Settings::get($apiUrlKey);
    $requestHandler = new RequestHandler($apiUrl . '/');
    $this->applicationService = new ApplicationService($requestHandler);
    $this->filtersService = new FilterService($requestHandler);
    $this->apartmentService = new ApartmentService($requestHandler);
  }

  /**
   * Get application service.
   */
  public function getApplicationService(): ApplicationService {
    return $this->applicationService;
  }

  /**
   * Get Filters service.
   */
  public function getFiltersService(): FilterService {
    return $this->filtersService;
  }

  /**
   * Get Apartment Service.
   */
  public function getApartmentService(): ApartmentService {
    return $this->apartmentService;
  }

}
