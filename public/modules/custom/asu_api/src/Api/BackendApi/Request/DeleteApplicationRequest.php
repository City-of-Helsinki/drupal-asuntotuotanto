<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\BackendApi\Response\DeleteApplicationResponse;
use Drupal\user\UserInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class DeleteApplicationRequest extends Request {

  protected const AUTHENTICATED = TRUE;
  protected string $method = 'DELETE';

  protected string $applicationId;
  protected array $payload;

  public function __construct(
    ?UserInterface $sender,
    string $applicationId,
    array $payload = []
  ) {
    if ($sender) {
      $this->setSender($sender);
    }
    $this->applicationId = $applicationId;
    $this->payload = $payload ?: [
      'comment' => 'Cancelled by user',
      'cancellation_reason' => 'terminated',
    ];
  }

  public function getPath(): string {
    return "/v1/applications/delete/{$this->applicationId}/";
  }

  public static function getResponse(ResponseInterface $response): DeleteApplicationResponse {
    return DeleteApplicationResponse::createFromHttpResponse($response);
  }

  public function getPayload(): array {
    return $this->payload;
  }

  public function toArray(): array {
    return $this->getPayload();
  }

  public function getMethod(): string {
    return 'DELETE';
  }
}
