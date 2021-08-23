<?php

namespace Drupal\asu_api\Api\ElasticSearchApi\Service;

use Drupal\asu_api\Api\ElasticSearchApi\Request\ProjectApartmentsRequest;
use Drupal\asu_api\Api\ElasticSearchApi\Request\ProxyRequest;
use Drupal\asu_api\Api\ElasticSearchApi\Request\SingleApartmentRequest;
use Drupal\asu_api\Api\ElasticSearchApi\Response\ProjectApartmentsResponse;
use Drupal\asu_api\Api\ElasticSearchApi\Response\ProxyResponse;
use Drupal\asu_api\Api\ElasticSearchApi\Response\SingleApartmentResponse;
use Drupal\asu_api\Api\ServiceBase;

/**
 * Endpoints for api's related to apartments.
 */
class ApartmentService extends ServiceBase {

  /**
   * Get project apartments.
   *
   * @param \Drupal\asu_api\Api\ElasticSearchApi\Request\ProjectApartmentsRequest $apartmentRequest
   *   Apartment request.
   *
   * @return \Drupal\asu_api\Api\ElasticSearchApi\Response\ProjectApartmentsResponse
   *   Apartment response.
   *
   * @throws \Exception
   */
  public function getProjectApartments(ProjectApartmentsRequest $apartmentRequest): ProjectApartmentsResponse {
    $options = [
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
      'json' => $apartmentRequest->toArray(),
    ];
    $response = $this->requestHandler->post($apartmentRequest->getPath(), $options);
    return ProjectApartmentsResponse::createFromHttpResponse($response);
  }

  /**
   * Get apartment by id.
   *
   * @param $id
   *   Id if aoartment
   *
   * @return \Drupal\asu_api\Api\ElasticSearchApi\Response\SingleApartmentResponse
   *
   * @throws \Exception
   */
  public function getApartment($id): SingleApartmentResponse {
    $request = new SingleApartmentRequest($id);
    $options = [
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
      'json' => $request->toArray(),
    ];
    $response = $this->requestHandler->post($request->getPath(), $options);
    return SingleApartmentResponse::createFromHttpResponse($response);
  }

  /**
   * Elasticsearch request proxy.
   */
  public function proxyRequest(array $request): ProxyResponse {
    $proxyRequest = new ProxyRequest($request);
    $query = $proxyRequest->getPath();
    if ($request) {
      $query .= '?source_content_type=application/json&source=' . json_encode($request);
    }
    $response = $this->requestHandler->get($query);
    return ProxyResponse::createFromHttpResponse($response);
  }

}
