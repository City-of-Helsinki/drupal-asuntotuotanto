<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\Request;
use Drupal\user\UserInterface;

/**
 * Create user request.
 */
class CreateUserRequest extends Request {
  protected const METHOD = 'POST';

  protected const PATH = '/v1/profiles/';

  protected const AUTHENTICATED = FALSE;

  /**
   * User data in array.
   *
   * @var array
   */
  private UserInterface $user;

  /**
   * User information.
   *
   * @var array
   */
  private array $userInformation;

  /**
   * Construct.
   */
  public function __construct(UserInterface $user, array $userInformation) {
    $this->user = $user;
    $this->userInformation = $userInformation;
  }

  /**
   * Data to array.
   */
  public function toArray(): array {
    $config = \Drupal::config('asu_user.external_user_fields');
    $fieldMap = $config->get('external_data_map');

    $data = [
      'id' => $this->user->uuid(),
      'email' => $this->user->getEmail(),
    ];

    if ($this->userInformation) {
      foreach ($fieldMap as $field => $information) {
        $data[$information['external_field']] = $this->userInformation[$field];
      }
    }

    // @todo Onko väärä datetime.
    $dateOfBirth = (new \DateTime($this->user->date_of_birth->value))->format('Y-m-d');
    $data['date_of_birth'] = $dateOfBirth;
    $data['contact_language'] = $this->user->getPreferredLangcode();

    return $data;
  }

}
