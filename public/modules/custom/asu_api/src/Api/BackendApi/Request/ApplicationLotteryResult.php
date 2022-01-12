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
   * Profile uuid.
   *
   * @var string
   */
  private string $profileUuid;

  /**
   * Project uuid.
   *
   * @var string
   */
  private string $projectUuid;

  /**
   * Constructor.
   *
   * @param string $profileUuid
   *   User profile uuid.
   * @param string $projectUuid
   *   Project uuid.
   */
  public function __construct(
    string $profileUuid,
    string $projectUuid
  ) {
    $this->profileUuid = $profileUuid;
    $this->projectUuid = $projectUuid;
  }

  /**
   * {@inheritDoc}
   */
  public function getPath(): string {
    $path = parent::getPath();

    $pathVariables = [
      '{profile_uuid}' => $this->profileUuid,
      '{project_uuid}' => $this->projectUuid,
    ];

    foreach ($pathVariables as $search => $replace) {
      $path = str_replace($search, $replace, $path);
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): ApplicationLotteryResultResponse {
    return ApplicationLotteryResultResponse::createFromHttpResponse($response);
  }

}
