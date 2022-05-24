<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\asu_api\Api\BackendApi\Response\CreateUserResponse;
use Drupal\asu_migration\UuidService;
use Drupal\asu_migration\AsuMigrationBase;
use Drupal\user\Entity\User;

/**
 * Migration service for user.
 */
class UserMigrationService extends AsuMigrationBase
{

  private array $drupalUserFields;

  private array $externalFields;

  /**
   * Constructor.
   */
  public function __construct(UuidService $uuidService, BackendApi $backendApi, private string $filePath, private string $uuidNamespace)
  {
    parent::__construct($uuidService, $backendApi);
    $this->externalFields = \Drupal::config('asu_user.external_user_fields')
      ->get('external_data_map');

    $this->externalFields['street_address'] = $this->externalFields['address'];

    $this->drupalUserFields = [
      'email', 'first_name', 'last_name'
    ];

  }

  public function migrate(): array
  {
    if (!file_exists($this->filePath)) {
      return ['User file is missing!'];
    }
    $this->file = fopen($this->filePath, 'r');

    $errors = [];
    $headers = [];
    foreach ($this->rows() as $row) {
      if (empty($headers)) {
        $headers = $row;
        continue;
      }
      $values = array_combine($headers, $row);

      $error = FALSE;
      $exception = NULL;

      try {
        $this->validateUserFields($values);
      }
      catch(\Exception $e) {
        $error = TRUE;
        $errors[$values['id']] = $e->getMessage();
        continue;
      }

      $externalFields = $this->mapExternalFields($values);

      try {
        $user = User::create([
          'uuid' => $this->uuidService->createUuid_v5($this->uuidNamespace, $values['id']),
          'mail' => $values['email'],
          'first_name' => $values['first_name'],
          'last_name' => $values['last_name'],
          'type' => 'customer',
          'langcode', 'fi',
          'preferred_langcode', 'fi',
          'preferred_admin_langcode', 'fi'
        ]);
        $user->save();
      }
      catch(\Exception $e) {
        $error = TRUE;
        $exception = $e->getMessage();
      }

      // Create backend user for the user.
      if (!$error && $user) {
        try {
          
          $request = new CreateUserRequest($user, $externalFields);
          /** @var CreateUserResponse $response */

          $response = $this->backendApi->send($request);

          $user->field_backend_profile = $response->getProfileId();
          $user->field_backend_password = $response->getPassword();
          $user->save();

          continue;
        }
        catch(\Exception $e) {
          $error = true;
          $exception = $e->getMessage();
        }
      }

      // If something went wrong, add to a list.
      $errors[$values['id']] = $exception;
    }
    return $errors;
  }

  private function validateUserFields(array $values) {
    foreach($this->drupalUserFields as $fieldName) {
      if (empty($values[$fieldName])) {
        throw new \Exception("Required field not found for user: $fieldName");
      }
    }
  }

  /**
   * @param array $values
   *   Csv data.
   * @return array
   *   Array with csv data mapped for api request.
   */
  private function mapExternalFields(array $values): array {
    $data = [];
    foreach($this->externalFields as $field => $information) {
      $data[$information['external_field']] = $values[$field] ?? '-';
    }
    return $data;
  }

}
