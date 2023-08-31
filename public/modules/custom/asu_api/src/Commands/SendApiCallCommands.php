<?php

namespace Drupal\asu_api\Commands;

use Drupal\asu_api\Api\BackendApi\Request\GetApartmentRevaluationsRequest;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\asu_api\Commands
 */
class SendApiCallCommands extends DrushCommands {

  /**
   * Drush.
   *
   * @command asu_api:sendApiCall
   * @aliases asu:send-api-call asu:sac
   * @usage asu_api:sendApiCall
   */
  public function sendApiCall() {
    try {
      /** @var \Drupal\asu_api\Api\BackendApi\BackendApi $api */
      $api = \Drupal::service('asu_api.backendapi');
      $request = new GetApartmentRevaluationsRequest();
      /** @var \Drupal\asu_api\Api\BackendApi\Response\GetApartmentRevaluationsResponse $response */
      $response = $api->send($request);
      $apartment_revaluation = $response->getContent();
      $this->output()->writeln(json_encode($apartment_revaluation));
    }
    catch (\Exception $e) {
      $this->output()->writeln($e->getMessage());
    }
  }

}
