<?php

namespace Drupal\asu_rest;

use Drupal\user\UserInterface;

/**
 * Object to handle user for api call.
 */
class UserDto {

  /**
   * User object.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $user;

  /**
   * Constructor.
   */
  public function __construct(UserInterface $user) {
    $this->user = $user;
  }

  /**
   * Get user as an array.
   *
   * @return array
   *   User as an array.
   */
  public function toArray(): array {
    return [
      'user_id' => $this->user->id(),
      'email_address' => $this->user->getEmail(),
      'username' => $this->user->getAccountName(),
      'applications' => [],
    ];
  }

  /**
   * User to array.
   *
   * @param \Drupal\user\UserInterface $user
   *   User object.
   *
   * @return static
   *   Self.
   */
  public static function createFromUser(UserInterface $user): self {
    return new self($user);
  }

}
