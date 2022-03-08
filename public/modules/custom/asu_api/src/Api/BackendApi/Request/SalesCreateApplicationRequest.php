<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\SalesCreateApplicationResponse;
use Drupal\asu_application\Entity\Application;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A request to create an application.
 */
final class SalesCreateApplicationRequest extends CreateApplicationRequest {
  protected const PATH = '/v1/sales/applications/';
  protected const METHOD = 'POST';
  protected const AUTHENTICATED = TRUE;

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $values = parent::toArray();
    /** @var \Drupal\user\UserInterface $owner */
    $owner = $this->application->getOwner();
    $values['profile'] = $owner->uuid();
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): SalesCreateApplicationResponse {
    return SalesCreateApplicationResponse::createFromHttpResponse($response);
  }

}
