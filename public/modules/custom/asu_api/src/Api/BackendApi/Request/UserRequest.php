<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\Request;
use Drupal\user\Entity\User;

/**
 * Request user information from backend.
 */
class UserRequest extends Request {
  protected const METHOD = 'GET';
  protected const PATH = '/v1/profiles/';

  /**
   * User object.
   *
   * @var Drupal\user\Entity\User
   */
  private User $user;

  /**
   * Constructor.
   *
   * @param Drupal\user\Entity\User $user
   *   User object.
   */
  public function __construct(User $user) {
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
    return $this->user->field_backend_profile->value;
  }

  /**
   * User request data to array.
   */
  public function toArray(): array {
    return ['id' => $this->user->uuid()];
  }

}
