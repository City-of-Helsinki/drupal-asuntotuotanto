<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\RequestException;
use Drupal\asu_api\Exception\ResponseParameterException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for user creation request.
 */
class CreateUserResponse extends Response {

  /**
   * Backend api profile id.
   *
   * @var string
   *   Backend profile id.
   */
  private string $profileId;

  /**
   * Backend api password.
   *
   * @var string
   *   Backend password.
   */
  private string $password;

  /**
   * Constructor.
   *
   * @param array $content
   *   Contents of the response.
   */
  public function __construct(array $content) {
    if (!$content['profile_id']) {
      throw new ResponseParameterException('No profile id returned on user creation');
    }
    if (!$content['password']) {
      throw new ResponseParameterException('No password returned on user creation');
    }
    $this->profileId = $content['profile_id'];
    $this->password = $content['password'];
  }

  /**
   * Get profile id returned by create user request.
   *
   * @return string
   *   Profile id in authentication request.
   */
  public function getProfileId(): string {
    return $this->profileId;
  }

  /**
   * Get password returned by create user request.
   *
   * @return string
   *   Password used in authentication request.
   */
  public function getPassword(): string {
    return $this->password;
  }

  /**
   * Create new user response from http response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   Guzzle response.
   *
   * @return CreateUserResponse
   *   CreateUserResponse.
   *
   * @throws \Exception
   */
  public static function createFromHttpResponse(ResponseInterface $response): CreateUserResponse {
    if (!self::requestOk($response)) {
      throw new RequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

}
