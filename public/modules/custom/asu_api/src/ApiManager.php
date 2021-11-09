<?php

namespace Drupal\asu_api;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\Response;

class ApiManager {

  private BackendApi $backendApi;

  public function __construct(BackendApi $backendApi) {
    $this->backendApi = $backendApi;
  }

  public function handleBackendRequest(Request $request, array $options = []): Response {
    return $this->backendApi->send($request, $options);
  }

  public static function handleElasticRequest() {
  }
}
