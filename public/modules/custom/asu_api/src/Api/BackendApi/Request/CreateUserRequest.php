<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Psr\Http\Message\ResponseInterface;
use Drupal\asu_api\Api\BackendApi\Response\CreateUserResponse;
use Drupal\asu_api\Api\Request;
use Drupal\user\UserInterface;

/**
 * A request to create new backend user.
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
   * Customer or sales.
   *
   * @var string
   */
  private string $accountType;

  /**
   * Construct.
   */
  public function __construct(UserInterface $user, array $userInformation, string $accountType = 'customer') {
    $this->user = $user;
    $this->userInformation = $userInformation;
    $this->accountType = $accountType;
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

    if ($this->accountType == 'customer' && $this->userInformation) {
      foreach ($fieldMap as $field => $information) {
        $data[$information['external_field']] = isset($this->userInformation[$field]) ? $this->userInformation[$field] : '';
      }
    }

    // @todo Onko väärä datetime.
    $dateOfBirth = (new \DateTime($this->user->date_of_birth->value))->format('Y-m-d');
    $data['date_of_birth'] = $dateOfBirth;
    $data['contact_language'] = $this->user->getPreferredLangcode();
    $data['account_type'] = $this->accountType;

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): CreateUserResponse {
    return CreateUserResponse::createFromHttpResponse($response);
  }

}
