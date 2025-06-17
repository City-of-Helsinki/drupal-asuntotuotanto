<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\BackendApi\Response\DeleteApplicationResponse;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Request for deleting an application.
 */
class DeleteApplicationRequest extends Request {

  protected const AUTHENTICATED = TRUE;

  /**
   * Which HTTP method to use for deletion.
   *
   * @var string
   */
  protected string $method = 'DELETE';

  /**
   * Id of the application to delete.
   *
   * @var string
   */

  protected string $applicationId;
  /**
   * Request of payload.
   *
   * @var array
   */
  protected array $payload;

  public function __construct(
    ?UserInterface $sender,
    string $applicationId,
    array $payload = [],
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

  /**
   * Gets path for application to be deleted.
   */
  public function getPath(): string {
    return "/v1/applications/delete/{$this->applicationId}/";
  }

  /**
   * Performs delete request for application.
   */
  public static function getResponse(ResponseInterface $response): DeleteApplicationResponse {
    return DeleteApplicationResponse::createFromHttpResponse($response);
  }

  /**
   * Getter for payload.
   */
  public function getPayload(): array {
    return $this->payload;
  }

  /**
   * Alias for getPayLoad()
   */
  public function toArray(): array {
    return $this->getPayload();
  }

  /**
   * Getter for HTTP method used.
   */
  public function getMethod(): string {
    return 'DELETE';
  }

}
