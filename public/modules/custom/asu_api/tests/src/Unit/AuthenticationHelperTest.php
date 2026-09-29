<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_api\Unit;

use Drupal\asu_api\Helper\AuthenticationHelper;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;

/**
 * Tests AuthenticationHelper token store key helpers.
 *
 * @group asu_api
 *
 * @coversDefaultClass \Drupal\asu_api\Helper\AuthenticationHelper
 */
final class AuthenticationHelperTest extends UnitTestCase {

  /**
   * Token store key includes backend profile id when present.
   *
   * - Account has field_backend_profile value.
   * - Store key is scoped to that profile id.
   */
  public function testTokenStoreKeyUsesBackendProfileId(): void {
    $account = $this->createAccountWithBackendProfile('profile-abc', '10');

    $this->assertSame(
      'asu_api_token:profile-abc',
      AuthenticationHelper::getTokenStoreKey($account)
    );
  }

  /**
   * Token store key falls back to Drupal uid when profile is missing.
   *
   * - Account has empty field_backend_profile.
   * - Store key is scoped to the Drupal user id.
   */
  public function testTokenStoreKeyFallsBackToUserId(): void {
    $account = $this->createAccountWithBackendProfile('', '152');

    $this->assertSame(
      'asu_api_token:uid:152',
      AuthenticationHelper::getTokenStoreKey($account)
    );
  }

  /**
   * Different senders must not share the same token store key.
   *
   * - Admin and customer have different backend profile ids.
   * - Their token store keys differ so one JWT cannot be reused for the other.
   */
  public function testDifferentSendersGetDifferentTokenStoreKeys(): void {
    $admin = $this->createAccountWithBackendProfile('admin-profile', '1');
    $customer = $this->createAccountWithBackendProfile('customer-profile', '152');

    $this->assertNotSame(
      AuthenticationHelper::getTokenStoreKey($admin),
      AuthenticationHelper::getTokenStoreKey($customer)
    );
  }

  /**
   * Build a user mock with backend profile field value.
   *
   * @param string $profileId
   *   Backend profile id, or empty string.
   * @param string $uid
   *   Drupal user id.
   *
   * @return \Drupal\user\UserInterface
   *   User mock.
   */
  private function createAccountWithBackendProfile(string $profileId, string $uid): UserInterface {
    $field = new class($profileId) {

      /**
       * Constructor.
       */
      public function __construct(private readonly string $profileId) {}

      /**
       * Mimic FieldItemList::__get('value').
       */
      public function __get(string $name) {
        if ($name === 'value') {
          return $this->profileId === '' ? NULL : $this->profileId;
        }
        return NULL;
      }

    };

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasField')->with('field_backend_profile')->willReturn(TRUE);
    $account->method('get')->with('field_backend_profile')->willReturn($field);

    return $account;
  }

}
