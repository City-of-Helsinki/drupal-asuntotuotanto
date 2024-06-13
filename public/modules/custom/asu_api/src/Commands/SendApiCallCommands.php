<?php

namespace Drupal\asu_api\Commands;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\GetApartmentRevaluationsRequest;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\asu_api\Commands
 */
class SendApiCallCommands extends DrushCommands {

  /**
   * Backend api.
   *
   * @var Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  /**
   * Constructor.
   */
  public function __construct(BackendApi $backendApi) {
    $this->backendApi = $backendApi;
  }

  /**
   * Drush.
   *
   * @command asu_api:sendApiCall
   * @aliases asu:send-api-call asu:sac
   * @usage asu_api:sendApiCall
   */
  public function sendApiCall() {
    try {
      $request = new GetApartmentRevaluationsRequest();
      /** @var \Drupal\asu_api\Api\BackendApi\Response\GetApartmentRevaluationsResponse $response */
      $response = $this->backendApi->send($request);
      $apartment_revaluation = $response->getContent();
      $this->output()->writeln(json_encode($apartment_revaluation));
    }
    catch (\Exception $e) {
      $this->output()->writeln($e->getMessage());
    }
  }

}
