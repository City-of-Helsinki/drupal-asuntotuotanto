<?php


namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\ApplicationLotteryResultResponse;
use Drupal\asu_api\Api\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * A request to create an application.
 */
class ApplicationLotteryResult extends Request
{
  protected const PATH = 'FILL THIS';

  protected const METHOD = 'POST';

  protected const AUTHENTICATED = TRUE;

  private string $projectId;

  /**
   * Constructor.
   */
  public function __construct(
    string $projectId
  )
  {
    $this->projectId = $projectId;
  }

  /**
   * Data to array.
   *
   * @return array
   *   Array which is sent to API.
   */
  public function toArray(): array
  {
    return [
      'project_id' => $this->projectId
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): ApplicationLotteryResultResponse
  {
    return ApplicationLotteryResultResponse::createFromHttpResponse($response);
  }

}
