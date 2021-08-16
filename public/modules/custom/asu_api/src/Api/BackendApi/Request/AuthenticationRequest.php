<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\Request;
use Drupal\user\UserInterface;

/**
 * Authentication request class.
 */
class AuthenticationRequest extends Request {
  protected const METHOD = 'POST';

  protected const PATH = '/v1/token/';

  protected const AUTHENTICATED = FALSE;

  /**
   * Current user.
   *
   * @var Drupal\user\UserInterface
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
   * Post request parameters for authentication request.
   */
  public function toArray(): array {
    return [
      'form_params' => [
        'profile_id' => $this->user->field_backend_profile->value,
        'password' => $this->user->field_backend_password->value,
      ],
    ];
  }

}
