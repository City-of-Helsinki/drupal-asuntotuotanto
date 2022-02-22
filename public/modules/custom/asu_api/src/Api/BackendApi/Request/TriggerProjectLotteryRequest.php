<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\TriggerProjectLotteryResponse;
use Drupal\asu_api\Api\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Request to trigger lottery for project.
 */
class TriggerProjectLotteryRequest extends Request {
  protected const AUTHENTICATED = TRUE;
  protected const PATH = '/v1/sales/execute_lottery_for_project';
  protected const METHOD = 'POST';

  /**
   * Constructor.
   *
   * @param string $project_uuid
   *   Project to trigger.
   */
  public function __construct(private string $project_uuid) {
  }

  /**
   * {@inheritDoc}
   */
  public function toArray(): array {
    return [
      'project_uuid' => $this->project_uuid,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function getResponse(ResponseInterface $response): TriggerProjectLotteryResponse {
    return TriggerProjectLotteryResponse::createFromHttpResponse($response);
  }

}
