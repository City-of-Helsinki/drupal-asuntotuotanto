<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\TriggerProjectLotteryResponse;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\Response;
use Psr\Http\Message\ResponseInterface;

class TriggerProjectLotteryRequest extends Request {
  protected const AUTHENTICATED = TRUE;
  protected const PATH = '/v1/sales/execute_lottery_for_project';
  protected const METHOD = 'POST';

  public function __construct(private string $project_uuid)
  {
  }

  public function toArray(): array
  {
    return [
      'project_uuid' => $this->project_uuid,
    ];
  }

  public static function getResponse(ResponseInterface $response): TriggerProjectLotteryResponse
  {
    return TriggerProjectLotteryResponse::createFromHttpResponse($response);
  }
}
