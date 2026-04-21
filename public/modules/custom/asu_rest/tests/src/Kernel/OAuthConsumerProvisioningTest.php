<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests OAuth consumer provisioning for REST API clients.
 *
 * @group asu_rest
 */
final class OAuthConsumerProvisioningTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'image',
    'serialization',
    'rest',
    'consumers',
    'simple_oauth',
    'asu_rest',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('consumer');

    // Needed by consumers/simple_oauth entities.
    $this->installConfig([
      'system',
      'user',
      'consumers',
      'simple_oauth',
    ]);

    // Role expected to exist via CMI in real environments.
    Role::create([
      'id' => 'rest_client',
      'label' => 'REST api client',
    ])->save();

    // asu_rest provisioning requires the rest_client scope when the
    // oauth2_scope entity type exists in the environment.
    $entity_type_manager = $this->container->get('entity_type.manager');
    if ($entity_type_manager->hasDefinition('oauth2_scope')) {
      $scope_storage = $entity_type_manager->getStorage('oauth2_scope');
      if (!$scope_storage->load('rest_client')) {
        $scope_storage->create([
          'id' => 'rest_client',
          'label' => 'REST client',
          'description' => 'REST client scope for automated consumers.',
        ])->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    putenv('ASU_REST_OAUTH_CLIENT_SECRET');
    putenv('ASU_REST_OAUTH_CLIENT_ID');
    putenv('ASU_REST_OAUTH_DEFAULT_USERNAME');
    parent::tearDown();
  }

  /**
   * Provisioning creates a REST client user and assigns it to the consumer.
   */
  public function testProvisioningCreatesUserAndAssignsConsumer(): void {
    putenv('ASU_REST_OAUTH_CLIENT_SECRET=test-secret');
    // Ensure container environment does not override configured client_id.
    putenv('ASU_REST_OAUTH_CLIENT_ID=apartment_application_service');
    putenv('ASU_REST_OAUTH_DEFAULT_USERNAME=rest_client');

    $this->config('asu_rest.settings')
      ->set('oauth_consumer', [
        'auto_create' => TRUE,
        'client_id' => 'apartment_application_service',
        'label' => 'Apartment application service',
      ])
      ->save();

    $this->container->get('module_handler')->loadInclude('asu_rest', 'install');
    call_user_func('asu_rest_provision_oauth_consumer');

    $users = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['name' => 'rest_client']);
    /** @var \Drupal\user\Entity\User $user */
    $user = reset($users);
    $this->assertInstanceOf(User::class, $user);
    $this->assertTrue($user->isActive());
    $this->assertTrue(in_array('rest_client', $user->getRoles(), TRUE));

    $consumer_storage = $this->container->get('entity_type.manager')
      ->getStorage('consumer');
    $consumer_ids = $consumer_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('client_id', 'apartment_application_service')
      ->range(0, 1)
      ->execute();
    $this->assertNotEmpty($consumer_ids);

    /** @var \Drupal\consumers\Entity\Consumer $consumer */
    $consumer = $consumer_storage->load(reset($consumer_ids));
    $this->assertInstanceOf(Consumer::class, $consumer);
    $this->assertSame((int) $user->id(), (int) $consumer->get('user_id')->target_id);
  }

}
