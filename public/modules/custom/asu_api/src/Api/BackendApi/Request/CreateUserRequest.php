<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\CreateUserResponse;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Helper\ApplicationHelper;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A request to create new backend user.
 */
class CreateUserRequest extends Request {
  protected const METHOD = 'POST';
  protected const PATH = '/v1/profiles/';
  protected const AUTHENTICATED = FALSE;

  /**
   * User to be sent to backend.
   *
   * @var array
   */
  private UserInterface $user;

  /**
   * Array of fields mentioned in external data map config file.
   *
   * @var array
   */
  private array $userInformation;

  /**
   * Customer or salesperson.
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
      'email' => $this->user->getEmail() ?? '',
      'contact_language' => $this->user->getPreferredLangcode(),
      'is_salesperson' => FALSE,
    ];

    if ($this->userInformation) {
      foreach ($fieldMap as $field => $information) {
        $data[$information['external_field']] = $this->userInformation[$field] ?? '';
      }

      if (!empty($this->userInformation['national_identification_number'])) {
        $data['national_identification_number'] = $this->userInformation['national_identification_number'];
      }
    }

    if ($this->accountType != 'customer') {
      $data['is_salesperson'] = TRUE;
    }

    if (isset($this->userInformation['date_of_birth'])) {
      try {
        $data['date_of_birth'] = ApplicationHelper::formatDate($this->userInformation['date_of_birth']);
      }
      catch (\Exception $e) {
        $data['date_of_birth'] = '1900-01-01';
      }
    }

    $null_to_string = ['city', 'phone_number', 'postal_code', 'street_address'];
    foreach ($data as $field => $item) {
      if (in_array($field, $null_to_string) && empty($item)) {
        $data[$field] = '__NULL__';
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): CreateUserResponse {
    return CreateUserResponse::createFromHttpResponse($response);
  }

}
