<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\asu_rest\Plugin\rest\resource\ApplicationMessages;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests message API contract for attachments.
 *
 * @group asu_rest
 */
final class ApplicationMessagesAttachmentsContractTest extends KernelTestBase {

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
    'file',
    'serialization',
    'rest',
    'asu_api',
    'asu_application',
    'asu_rest',
  ];

  /**
   * Application owner.
   */
  private User $owner;

  /**
   * Created application entity.
   */
  private Application $application;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['node']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('application_type');
    $this->installSchema('system', ['sequences']);

    ApplicationType::create([
      'id' => 'hitas',
      'label' => 'Hitas',
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $this->installEntitySchema('asu_application');
    $this->installEntitySchema('asu_application_message');

    if (!Role::load('customer')) {
      Role::create([
        'id' => 'customer',
        'label' => 'Customer',
      ])->save();
    }

    $this->container->get('module_handler')->loadInclude('asu_application', 'install');
    asu_application_update_9019();

    $this->owner = User::create([
      'name' => 'owner-customer',
      'mail' => 'owner-customer@example.com',
      'status' => 1,
    ]);
    $this->owner->addRole('customer');
    $this->owner->save();

    $this->container->get('current_user')->setAccount($this->owner);
    $request = Request::create('/application/add/hitas/42', 'GET');
    $request->setSession($this->container->get('request_stack')->getCurrentRequest()->getSession());
    $this->container->get('request_stack')->push($request);

    $this->application = Application::create([
      'bundle' => 'hitas',
      'project_id' => 42,
      'status' => 1,
    ]);
    $this->application->save();
  }

  /**
   * Create-message accepts attachment_ids and thread returns attachments.
   */
  public function testCreateAndReadThreadWithAttachments(): void {
    $attachmentId = $this->createAttachment('contract.pdf', 'application/pdf');

    $request = Request::create('/applications/' . (int) $this->application->id() . '/messages', 'POST');
    $request->attributes->set('application_id', (int) $this->application->id());
    $current = $this->container->get('request_stack')->getCurrentRequest();
    if ($current && $current->hasSession()) {
      $request->setSession($current->getSession());
    }
    $this->container->get('request_stack')->push($request);

    $this->container->get('current_user')->setAccount($this->owner);
    $resource = $this->resource();

    $postResponse = $resource->post([
      'sender_role' => 'customer',
      'body' => 'Message with attachment',
      'attachment_ids' => [$attachmentId],
    ]);

    $this->assertSame(200, $postResponse->getStatusCode());
    $postPayload = $postResponse->getResponseData();
    $this->assertIsArray($postPayload);
    $this->assertSame($attachmentId, (int) $postPayload['item']['attachments'][0]['id']);
    $this->assertArrayHasKey('download_url', $postPayload['item']['attachments'][0]);

    $getResponse = $resource->get((int) $this->application->id());
    $this->assertSame(200, $getResponse->getStatusCode());

    $getPayload = $getResponse->getResponseData();
    $this->assertIsArray($getPayload);
    $this->assertNotEmpty($getPayload['items']);
    $this->assertSame($attachmentId, (int) $getPayload['items'][0]['attachments'][0]['id']);
    $this->assertStringEndsWith('/user/application/attachments/' . $attachmentId . '/download', (string) $getPayload['items'][0]['attachments'][0]['download_url']);
  }

  /**
   * Creates attachment metadata row.
   */
  private function createAttachment(string $displayName, string $mimeType): int {
    $db = $this->container->get('database');
    $db->insert('asu_application_attachment')
      ->fields([
        'display_name' => $displayName,
        'mime_type' => $mimeType,
        'size_bytes' => 1024,
        'storage_uri' => 'private://contract/' . $displayName,
        'file_id' => 1,
        'media_id' => 0,
        'active' => 1,
        'customer_visible' => 1,
        'revoked' => 0,
        'archived' => 0,
        'created' => 1700000000,
        'created_by' => (int) $this->owner->id(),
      ])
      ->execute();

    return (int) $db->select('asu_application_attachment', 'a')
      ->fields('a', ['id'])
      ->condition('display_name', $displayName)
      ->condition('created_by', (int) $this->owner->id())
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns resource under test.
   */
  private function resource(): ApplicationMessages {
    /** @var \Drupal\rest\Plugin\Type\ResourcePluginManager $manager */
    $manager = $this->container->get('plugin.manager.rest');
    /** @var \Drupal\asu_rest\Plugin\rest\resource\ApplicationMessages $resource */
    $resource = $manager->createInstance('asu_application_messages', []);

    return $resource;
  }

}
