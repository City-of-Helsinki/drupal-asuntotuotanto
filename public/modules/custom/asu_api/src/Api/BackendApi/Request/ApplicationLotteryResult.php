<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\ApplicationLotteryResultResponse;
use Drupal\asu_api\Api\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * A request to create an application.
 */
class ApplicationLotteryResult extends Request {
  protected const METHOD = 'GET';
  protected const PATH = '/v1/profiles/me/projects/{project_uuid}/reservations';
  protected const AUTHENTICATED = TRUE;

  /**
   * Constructor.
   *
   * @param string $projectUuid
   *   Project uuid.
   */
  public function __construct(
    private string $projectUuid
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function getPath(): string {
    return str_replace('{project_uuid}', $this->projectUuid, parent::getPath());
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): ApplicationLotteryResultResponse {
    return ApplicationLotteryResultResponse::createFromHttpResponse($response);
  }

}
