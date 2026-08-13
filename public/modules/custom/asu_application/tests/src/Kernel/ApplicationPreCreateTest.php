<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Application::preCreate owner assignment for sales/admin create flow.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Entity\Application
 */
final class ApplicationPreCreateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'datetime',
    'asu_api',
    'asu_application',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('application_type');
    $this->installConfig(['node']);
    $this->installSchema('system', ['sequences']);

    ApplicationType::create([
      'id' => 'hitas',
      'label' => 'Hitas',
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->installEntitySchema('asu_application');

    if (!Role::load('customer')) {
      Role::create([
        'id' => 'customer',
        'label' => 'Customer',
      ])->save();
    }
  }

  /**
   * Admin create with user_id query assigns the customer as owner.
   *
   * - Current user is not a customer (e.g. admin on default user bundle).
   * - Request includes user_id for the customer.
   * - Created application owner is that customer, not the admin.
   * - created_admin is TRUE.
   */
  public function testAdminCreateUsesUserIdQueryParameter(): void {
    $customer = $this->createUserAccount(['customer'], 'customer-owner');
    $admin = $this->createUserAccount([], 'admin-creator');

    $this->container->get('current_user')->setAccount($admin);
    $this->pushRequestWithUserId((int) $customer->id());

    $application = Application::create([
      'bundle' => 'hitas',
      'project_id' => 42,
    ]);

    $this->assertSame((string) $customer->id(), (string) $application->getOwnerId());
    $this->assertTrue((bool) $application->get('created_admin')->value);
    $this->assertSame(
      (string) $admin->id(),
      (string) $application->get('created_by')->target_id
    );
  }

  /**
   * Customer create without user_id assigns themselves as owner.
   *
   * - Current user has the customer role.
   * - No user_id query parameter.
   * - Created application owner is the current customer.
   * - created_admin is FALSE.
   */
  public function testCustomerCreateUsesCurrentUserAsOwner(): void {
    $customer = $this->createUserAccount(['customer'], 'self-customer');
    $this->container->get('current_user')->setAccount($customer);
    $this->pushRequestWithoutUserId();

    $application = Application::create([
      'bundle' => 'hitas',
      'project_id' => 42,
    ]);

    $this->assertSame((string) $customer->id(), (string) $application->getOwnerId());
    $this->assertFalse((bool) $application->get('created_admin')->value);
  }

  /**
   * Non-customer create without user_id fails.
   *
   * - Current user is not a customer.
   * - Request has no user_id.
   * - preCreate throws because the customer owner is unknown.
   */
  public function testNonCustomerCreateWithoutUserIdThrows(): void {
    $admin = $this->createUserAccount([], 'admin-no-user');
    $this->container->get('current_user')->setAccount($admin);
    $this->pushRequestWithoutUserId();

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Tried to create new application without user.');

    Application::create([
      'bundle' => 'hitas',
      'project_id' => 42,
    ]);
  }

  /**
   * Create a user with roles.
   *
   * @param string[] $roles
   *   Role ids.
   * @param string $name
   *   Account name.
   *
   * @return \Drupal\user\Entity\User
   *   Created user.
   */
  private function createUserAccount(array $roles, string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.com',
      'status' => 1,
    ]);
    foreach ($roles as $role) {
      $user->addRole($role);
    }
    $user->save();

    return $user;
  }

  /**
   * Push a request that includes user_id for sales/admin create flow.
   *
   * @param int $userId
   *   Customer user id.
   */
  private function pushRequestWithUserId(int $userId): void {
    $request = Request::create(
      '/application/add/hitas/42',
      'GET',
      ['user_id' => $userId]
    );
    $request->setSession($this->container->get('request_stack')->getCurrentRequest()->getSession());
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Push a request without user_id, preserving the kernel test session.
   */
  private function pushRequestWithoutUserId(): void {
    $request = Request::create('/application/add/hitas/42', 'GET');
    $request->setSession($this->container->get('request_stack')->getCurrentRequest()->getSession());
    $this->container->get('request_stack')->push($request);
  }

}
