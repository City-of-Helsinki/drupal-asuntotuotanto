<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Drupal\asu_api\Api\BackendApi\Response\AuthenticationResponse;
use Drupal\asu_api\Api\Request;

/**
 * Request authentication token.
 */
class AuthenticationRequest extends Request {
  protected const METHOD = 'POST';
  protected const PATH = '/v1/token/';
  protected const AUTHENTICATED = TRUE;

  /**
   * The customer.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $user;

  /**
   * AuthenticationRequest constructor.
   *
   * @param \Drupal\user\UserInterface $user
   *   Current user.
   */
  public function __construct(UserInterface $user) {
    $this->user = $user;
  }

  /**
   * Get user.
   *
   * @return \Drupal\user\UserInterface
   *   Customer class.
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'profile_id' => $this->user->get('field_backend_profile'),
      'password' => $this->user->get('field_backend_password'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): AuthenticationResponse {
    return AuthenticationResponse::createFromHttpResponse($response);
  }

}
