<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\UserResponse;
use Drupal\asu_api\Api\Request;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Get user information from backend.
 */
class UserRequest extends Request {
  protected const METHOD = 'GET';
  protected const PATH = '/v1/profiles/';
  protected const AUTHENTICATED = TRUE;

  /**
   * User object.
   *
   * @var Drupal\user\UserInterface
   */
  private UserInterface $user;

  /**
   * Constructor.
   *
   * @param Drupal\user\UserInterface $user
   *   User object.
   */
  public function __construct(UserInterface $user) {
    $this->user = $user;
  }

  /**
   * {@inheritDoc}
   */
  public function getPath(): string {
    return static::PATH . $this->getBackendProfileId() . '/';
  }

  /**
   * Get users backend profile.
   */
  public function getBackendProfileId(): string {
    if (!$backendProfileId = $this->user->get('field_backend_profile')->value) {
      throw new \InvalidArgumentException('Trying to fetch user data for user without backend profile: user id ' . $this->user->id());
    }
    return $backendProfileId;
  }

  /**
   * Get user object.
   *
   * @return Drupal\user\Entity\User
   *   User object.
   */
  public function getUser() {
    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return ['id' => $this->user->uuid()];
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): UserResponse {
    return UserResponse::createFromHttpResponse($response);
  }

}
