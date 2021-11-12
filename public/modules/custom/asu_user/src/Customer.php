<?php

namespace Drupal\asu_user;

use Drupal\asu_api\Api\BackendApi\Helper\AuthenticationHelper;
use Drupal\asu_user\Helper\StoreHelper;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\user\Entity\User;

/**
 * A customer class.
 */
class Customer {

  private const API_TOKEN = 'asu_api_token';

  /**
   * User class.
   *
   * @var Drupal\user\Entity\User
   */
  private User $user;

  /**
   * Temporary store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  private PrivateTempStore $store;

  /**
   * Configuration.
   *
   * @var array|\Drupal\Core\Config\ImmutableConfig
   */
  private ?array $config;

  /**
   * Constructor.
   */
  public function __construct(AccountProxy $user, PrivateTempStoreFactory $storeFactory) {
    $this->user = User::load($user->id());
    $this->store = $storeFactory->get('customer');
    $this->config = \Drupal::config('asu_user.external_user_fields')
      ->get('external_data_map');
  }

  /**
   * Get field either from userentity or store.
   *
   * @param string $name
   *   Name of the field.
   *
   * @return string|null
   *   Field value.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getUserField(string $name): ?string {
    if ($this->user->hasField($name)) {
      return $this->user->get($name)->first()->value;
    }
    if ($this->user->bundle() != 'customer') {
      throw new \Exception('Trying to access external data of an user of a wrong type.');
    }
    return $this->store->get($name);
  }

  /**
   * Update user fields on store.
   */
  public function updateUserExternalFields(array $data) {
    StoreHelper::setMultipleValuesToStoreByConfiguration($this->store, $this->config, $data);
  }

  /**
   * Get stored field values.
   */
  public function getUserExternalFieldData() {
    $values = [];
    foreach ($this->config as $field => $value) {
      $values[$value['external_field']] = $this->store->get($field) ?? '-';
    }
    return $values;
  }

  /**
   * Set user authentication token.
   *
   * @param string $token
   *   Backend authentication token.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function setToken(string $token) {
    $this->store->set(self::API_TOKEN, $token);
  }

  /**
   * Get user authentication token.
   */
  public function getToken(): ?string {
    return $this->store->get(self::API_TOKEN);
  }

  /**
   * Check if user has a valid token for backend api.
   *
   * @return bool
   *   Is user able to send authenticated requests to backend.
   */
  public function hasValidAuthToken(): bool {
    if ($token = $this->getToken()) {
      return AuthenticationHelper::isTokenAlive($token);
    }
    return FALSE;
  }

  /**
   * Get customer class.
   */
  public static function getCustomer() {
    return new self(\Drupal::currentUser(), \Drupal::service('private.tempstore'));
  }

}
