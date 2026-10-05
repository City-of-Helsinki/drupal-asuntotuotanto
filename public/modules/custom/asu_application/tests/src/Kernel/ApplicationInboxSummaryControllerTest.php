<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\ApplicationMessageManager;
use Drupal\asu_application\Controller\ApplicationUnreadController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests sales inbox summary endpoint.
 *
 * @group asu_application
 */
final class ApplicationInboxSummaryControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'asu_api',
    'asu_application',
  ];

  /**
   * Message manager.
   *
   * @var \Drupal\asu_application\ApplicationMessageManager
   */
  private ApplicationMessageManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('asu_application_message');

    $this->manager = $this->container->get('asu_application.message_manager');

    // Reserve uid=1 to avoid accidental superuser permissions in tests.
    User::create([
      'name' => 'kernel-root-placeholder',
      'mail' => 'kernel-root-placeholder@example.com',
      'status' => 1,
    ])->save();
  }

  /**
   * Tests summary endpoint with explicit application ids.
   */
  public function testInboxSummaryWithApplicationIds(): void {
    $sales = $this->createUserWithRoles('sales@example.com', ['sales']);

    $messageA = $this->manager->createMessage(100, 10, 'Customer first message', 'customer');
    $messageA->set('created', 1000);
    $messageA->save();

    $longText = str_repeat('A', 230);
    $messageB = $this->manager->createMessage(101, 10, $longText, 'customer');
    $messageB->set('created', 2000);
    $messageB->save();

    $messageOutside = $this->manager->createMessage(102, 10, 'Outside filter', 'customer');
    $messageOutside->set('created', 3000);
    $messageOutside->save();

    $this->manager->markThreadRead((int) $sales->id(), 100, 1500);

    [$status, $payload] = $this->requestInboxSummary($sales, [
      'sales_shared' => '1',
      'application_ids' => '100,101',
    ]);

    $this->assertSame(200, $status);
    $this->assertArrayHasKey('items', $payload);
    $this->assertArrayHasKey('total_unread', $payload);

    $itemsById = [];
    foreach ($payload['items'] as $item) {
      $itemsById[(int) $item['application_id']] = $item;
    }

    $this->assertCount(2, $itemsById);
    $this->assertArrayHasKey(100, $itemsById);
    $this->assertArrayHasKey(101, $itemsById);

    $this->assertSame(0, (int) $itemsById[100]['unread_count']);
    $this->assertFalse((bool) $itemsById[100]['has_unread']);
    $this->assertSame('Customer first message', $itemsById[100]['last_message_preview']);
    $this->assertSame(gmdate('c', 1000), $itemsById[100]['last_message_at']);

    $this->assertSame(1, (int) $itemsById[101]['unread_count']);
    $this->assertTrue((bool) $itemsById[101]['has_unread']);
    $this->assertSame(200, mb_strlen((string) $itemsById[101]['last_message_preview']));
    $this->assertSame(gmdate('c', 2000), $itemsById[101]['last_message_at']);

    $this->assertSame(1, (int) $payload['total_unread']);
  }

  /**
   * Tests summary endpoint without application ids.
   */
  public function testInboxSummaryWithoutApplicationIds(): void {
    $sales = $this->createUserWithRoles('sales-no-ids@example.com', ['sales']);

    $messageA = $this->manager->createMessage(201, 20, 'Customer app 201', 'customer');
    $messageA->set('created', 4000);
    $messageA->save();

    $messageB = $this->manager->createMessage(202, 20, 'Customer app 202', 'customer');
    $messageB->set('created', 5000);
    $messageB->save();

    [$status, $payload] = $this->requestInboxSummary($sales, [
      'sales_shared' => '1',
    ]);

    $this->assertSame(200, $status);
    $itemsById = [];
    foreach ($payload['items'] as $item) {
      $itemsById[(int) $item['application_id']] = $item;
    }

    $this->assertCount(2, $itemsById);
    $this->assertArrayHasKey(201, $itemsById);
    $this->assertArrayHasKey(202, $itemsById);
    $this->assertSame(2, (int) $payload['total_unread']);
  }

  /**
   * Tests forbidden response for unauthorized non-sales users.
   */
  public function testInboxSummaryForbidden(): void {
    $customer = $this->createUserWithRoles('customer@example.com', []);

    [$status, $payload] = $this->requestInboxSummary($customer, [
      'sales_shared' => '1',
    ]);

    $this->assertSame(403, $status);
    $this->assertSame('forbidden', $payload['error']);
    $this->assertSame('Missing permission: view shared sales unread counts.', $payload['message']);
  }

  /**
   * Tests empty result when no customer messages are present.
   */
  public function testInboxSummaryEmptyResult(): void {
    $sales = $this->createUserWithRoles('sales-empty@example.com', ['sales']);

    [$status, $payload] = $this->requestInboxSummary($sales, [
      'sales_shared' => '1',
    ]);

    $this->assertSame(200, $status);
    $this->assertSame([], $payload['items']);
    $this->assertSame(0, (int) $payload['total_unread']);
  }

  /**
   * Tests that shared sales mark-read clears unread for permission-based user.
   */
  public function testMarkReadSalesSharedWithPermission(): void {
    $role = Role::create([
      'id' => 'rest_client',
      'label' => 'Rest Client',
    ]);
    $role->grantPermission('view shared sales unread counts');
    $role->save();

    $serviceUser = $this->createUserWithRoles('service@example.com', ['rest_client']);

    $message = $this->manager->createMessage(301, 30, 'Unread customer message', 'customer');
    $message->set('created', 7000);
    $message->save();

    [$beforeStatus, $beforePayload] = $this->requestUnreadCounts($serviceUser, [
      'sales_shared' => '1',
      'application_ids' => '301',
    ]);

    $this->assertSame(200, $beforeStatus);
    $this->assertSame(1, (int) ($beforePayload['counts'][301] ?? 0));

    [$markStatus, $markPayload] = $this->requestMarkRead($serviceUser, [
      'sales_shared' => '1',
    ], [
      'application_id' => '301',
    ]);

    $this->assertSame(200, $markStatus);
    $this->assertSame([301], $markPayload['updated_application_ids']);
    $this->assertNotEmpty($markPayload['updated_at']);

    [$afterStatus, $afterPayload] = $this->requestUnreadCounts($serviceUser, [
      'sales_shared' => '1',
      'application_ids' => '301',
    ]);

    $this->assertSame(200, $afterStatus);
    $this->assertSame(0, (int) ($afterPayload['counts'][301] ?? 0));
  }

  /**
   * Tests forbidden mark-read for unauthorized non-sales users.
   */
  public function testMarkReadForbidden(): void {
    $customer = $this->createUserWithRoles('mark-read-customer@example.com', []);

    [$status, $payload] = $this->requestMarkRead($customer, [
      'sales_shared' => '1',
    ], [
      'application_id' => '100',
    ]);

    $this->assertSame(403, $status);
    $this->assertSame('forbidden', $payload['error']);
    $this->assertSame('Missing permission: view shared sales unread counts.', $payload['message']);
  }

  /**
   * Creates user with optional extra roles.
   */
  private function createUserWithRoles(string $mail, array $roles): User {
    $account = User::create([
      'name' => $mail,
      'mail' => $mail,
      'status' => 1,
    ]);

    foreach ($roles as $role) {
      $account->addRole($role);
    }

    $account->save();

    return $account;
  }

  /**
   * Calls the inbox summary endpoint directly.
   *
   * @return array{0:int,1:array<string,mixed>}
   *   Response status and decoded payload.
   */
  private function requestInboxSummary(User $user, array $query): array {
    $request = Request::create('/fi/user/application/inbox-summary', 'GET', $query);

    /** @var \Drupal\asu_application\Controller\ApplicationUnreadController $controller */
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(ApplicationUnreadController::class);

    \Drupal::currentUser()->setAccount($user);
    $response = $controller->getInboxSummary($request);

    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertIsArray($payload);

    return [(int) $response->getStatusCode(), $payload];
  }

  /**
   * Calls unread-counts endpoint directly.
   *
   * @return array{0:int,1:array<string,mixed>}
   *   Response status and decoded payload.
   */
  private function requestUnreadCounts(User $user, array $query): array {
    $request = Request::create('/fi/user/application/unread-counts', 'GET', $query);

    /** @var \Drupal\asu_application\Controller\ApplicationUnreadController $controller */
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(ApplicationUnreadController::class);

    \Drupal::currentUser()->setAccount($user);
    $response = $controller->getUnreadCounts($request);

    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertIsArray($payload);

    return [(int) $response->getStatusCode(), $payload];
  }

  /**
   * Calls mark-read endpoint directly.
   *
   * @return array{0:int,1:array<string,mixed>}
   *   Response status and decoded payload.
   */
  private function requestMarkRead(User $user, array $query, array $payload): array {
    $request = Request::create('/fi/user/application/mark-read', 'POST', array_merge($query, $payload));

    /** @var \Drupal\asu_application\Controller\ApplicationUnreadController $controller */
    $controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(ApplicationUnreadController::class);

    \Drupal::currentUser()->setAccount($user);
    $response = $controller->markRead($request);

    $decoded = json_decode((string) $response->getContent(), TRUE);
    $this->assertIsArray($decoded);

    return [(int) $response->getStatusCode(), $decoded];
  }

}
