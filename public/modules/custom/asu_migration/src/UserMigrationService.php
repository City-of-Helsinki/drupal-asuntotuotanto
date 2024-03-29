<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\user\Entity\User;

/**
 * Migration service for user.
 */
class UserMigrationService extends AsuMigrationBase {

  /**
   * User fields array.
   *
   * @var string[]
   */
  private array $drupalUserFields;

  /**
   * Fields only saved to backend.
   *
   * @var array|mixed|null
   */
  private array $externalFields;

  /**
   * Constructor.
   */
  public function __construct(UuidService $uuidService, BackendApi $backendApi, private string $filePath, private string $uuidNamespace) {
    parent::__construct($uuidService, $backendApi);
    $this->externalFields = \Drupal::config('asu_user.external_user_fields')
      ->get('external_data_map');

    $this->externalFields['street_address'] = $this->externalFields['address'];

    $this->drupalUserFields = [
      'email', 'first_name', 'last_name',
    ];

  }

  /**
   * Handle migration.
   *
   * @return array
   *   Array of errors.
   */
  public function migrate(): array {
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

      if (empty($row)) {
        continue;
      }
      if (count($row) != count($headers)) {
        continue;
      }

      $values = array_combine($headers, $row);

      $error = FALSE;
      $exception = NULL;

      if (isset($values['phone_number']) && empty($values['phone_number'])) {
        $values['phone_number'] = '__NULL__';
      }

      if ((isset($values['email']) && empty($values['email']))
        || !\Drupal::service('email.validator')->isValid($values['email'])) {
        $values['email'] = $values['id'] . '_emailmissing@asuntotuotanto.com';
      }

      try {
        // $this->validateUserFields($values);
      }
      catch (\Exception $e) {
        $error = TRUE;
        $errors[$values['id']] = $e->getMessage();
        continue;
      }

      $externalFields = $this->mapExternalFields($values);

      // @todo What to do with email.
      try {
        $hash = substr(base64_encode(microtime()), 0, 6);
        $user = User::create([
          'uuid' => $this->uuidService->createUuidV5($this->uuidNamespace, $values['id']),
          'mail' => $values['email'],
          'name' => "{$values['first_name']}_{$values['last_name']}_$hash",
          'type' => 'customer',
          'langcode' => 'fi',
          'preferred_langcode' => 'fi',
          'preferred_admin_langcode' => 'fi',
          'status' => 1,
        ]);

        $user->save();
      }
      catch (\Exception $e) {
        $error = TRUE;
        $exception = $e->getMessage();
      }

      // Create backend user for the user.
      if (!$error && $user) {
        try {
          $externalFields['address'] = $externalFields['street_address'];
          $request = new CreateUserRequest($user, $externalFields);
          /** @var \Drupal\asu_api\Api\BackendApi\Response\CreateUserResponse $response */
          $response = $this->backendApi->send($request);

          $user->field_backend_profile = $response->getProfileId();
          $user->field_backend_password = $response->getPassword();
          $user->save();

          continue;
        }
        catch (\Exception $e) {
          $error = TRUE;
          $exception = $e->getMessage();
        }
      }

      // If something went wrong, add to a list.
      $errors[$values['id']] = $exception;
    }
    return $errors;
  }

  /**
   * Make sure all required fields are present.
   *
   * @param array $values
   *   Array of fields and values.
   *
   * @throws \Exception
   */
  private function validateUserFields(array $values) {
    foreach ($this->drupalUserFields as $fieldName) {
      if (empty($values[$fieldName])) {
        throw new \Exception("Required field not found for user: $fieldName");
      }
    }
  }

  /**
   * Map external fields to array.
   *
   * @param array $values
   *   Csv data.
   *
   * @return array
   *   Array with csv data mapped for api request.
   */
  private function mapExternalFields(array $values): array {
    $data = [];
    foreach ($this->externalFields as $field => $information) {
      $data[$information['external_field']] = $values[$field] ?? '-';
    }
    return $data;
  }

}
