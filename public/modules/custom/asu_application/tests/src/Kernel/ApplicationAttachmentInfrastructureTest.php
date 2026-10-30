<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\ApplicationAttachmentManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests attachment schema migration and security policy behavior.
 *
 * @group asu_application
 */
final class ApplicationAttachmentInfrastructureTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'asu_api',
    'asu_application',
  ];

  /**
   * Attachment manager service.
   */
  private ApplicationAttachmentManager $attachmentManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('asu_application_message');
    $this->installSchema('system', ['sequences']);

    $this->container->get('module_handler')->loadInclude('asu_application', 'install');
    $this->attachmentManager = $this->container->get('asu_application.attachment_manager');
  }

  /**
   * Attachment schema update is safe on clean and existing databases.
   */
  public function testAttachmentSchemaUpdateIsIdempotent(): void {
    asu_application_update_9019();

    $schema = $this->container->get('database')->schema();
    $this->assertTrue($schema->tableExists('asu_application_attachment'));
    $this->assertTrue($schema->tableExists('asu_application_message_attachment'));

    $db = $this->container->get('database');
    $db->insert('asu_application_attachment')
      ->fields([
        'display_name' => 'idempotent.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1000,
        'storage_uri' => 'private://idempotent.pdf',
        'file_id' => 1,
        'media_id' => 0,
        'active' => 1,
        'customer_visible' => 1,
        'revoked' => 0,
        'archived' => 0,
        'created' => 1700000000,
        'created_by' => 1,
      ])
      ->execute();

    $countBefore = (int) $db->query('SELECT COUNT(*) FROM {asu_application_attachment}')->fetchField();

    // Re-run update hook: should not fail and should not duplicate data.
    asu_application_update_9019();

    $countAfter = (int) $db->query('SELECT COUNT(*) FROM {asu_application_attachment}')->fetchField();
    $this->assertSame($countBefore, $countAfter);
  }

  /**
   * Settings update seeds defaults and is safe to re-run.
   */
  public function testSettingsUpdateSeedsDefaultsIdempotently(): void {
    asu_application_update_9020();

    $config = $this->config('asu_application.settings');
    $this->assertNotEmpty($config->get('attachments.allowed_mime_types'));
    $this->assertSame(20971520, (int) $config->get('attachments.max_file_size_bytes'));
    $this->assertSame(30, (int) $config->get('attachments.rate_limit.per_user'));
    $this->assertSame(20, (int) $config->get('attachments.rate_limit.per_attachment'));
    $this->assertSame(60, (int) $config->get('attachments.rate_limit.window_seconds'));

    // Re-run should not change existing customized values.
    $this->config('asu_application.settings')
      ->set('attachments.rate_limit.per_attachment', 7)
      ->save();

    asu_application_update_9020();

    $this->assertSame(7, (int) $this->config('asu_application.settings')->get('attachments.rate_limit.per_attachment'));
  }

  /**
   * MIME/size policy is loaded from config.
   */
  public function testValidationUsesConfigurablePolicy(): void {
    asu_application_update_9019();

    $this->config('asu_application.settings')
      ->set('attachments.allowed_mime_types', ['application/pdf'])
      ->set('attachments.max_file_size_bytes', 50)
      ->save();

    $db = $this->container->get('database');
    $db->insert('asu_application_attachment')
      ->fields([
        'display_name' => 'too-big.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'storage_uri' => 'private://too-big.pdf',
        'file_id' => 1,
        'media_id' => 0,
        'active' => 1,
        'customer_visible' => 1,
        'revoked' => 0,
        'archived' => 0,
        'created' => 1700000000,
        'created_by' => 1,
      ])
      ->execute();

    $id = (int) $db->select('asu_application_attachment', 'a')
      ->fields('a', ['id'])
      ->condition('display_name', 'too-big.pdf')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    $this->assertGreaterThan(0, $id);
    $validation = $this->attachmentManager->validateAttachableIds([$id]);

    $this->assertArrayHasKey($id, $validation['errors']);
    $this->assertSame('size_exceeded', $validation['errors'][$id]);
  }

  /**
   * Per-attachment rate limit denies second request at configured threshold.
   */
  public function testPerAttachmentRateLimitIsEnforced(): void {
    asu_application_update_9019();

    $this->config('asu_application.settings')
      ->set('attachments.rate_limit.per_user', 10)
      ->set('attachments.rate_limit.per_attachment', 1)
      ->set('attachments.rate_limit.window_seconds', 3600)
      ->save();

    $fileSystem = $this->container->get('file_system');
    $uri = $fileSystem->saveData('rate-limit-content', 'temporary://rate-limit.txt', \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);

    $file = \Drupal\file\Entity\File::create([
      'uri' => $uri,
      'filename' => 'rate-limit.txt',
      'filemime' => 'text/plain',
      'status' => 1,
    ]);
    $file->save();

    $db = $this->container->get('database');
    $attachmentId = (int) $db->insert('asu_application_attachment')
      ->fields([
        'display_name' => 'rate-limit.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 10,
        'storage_uri' => (string) $uri,
        'file_id' => (int) $file->id(),
        'media_id' => 0,
        'active' => 1,
        'customer_visible' => 1,
        'revoked' => 0,
        'archived' => 0,
        'created' => 1700000000,
        'created_by' => 1,
      ])
      ->execute();

    $this->assertGreaterThan(0, $attachmentId);

    $storage = $this->container->get('entity_type.manager')->getStorage('asu_application_message');
    $message = $storage->create([
      'application_id' => 9010,
      'project_id' => 7001,
      'sender_role' => 'sales',
      'recipient_mail' => 'ratelimit@example.com',
      'body' => 'rate-limit message',
      'created' => 1700000000,
    ]);
    $message->save();

    $db->insert('asu_application_message_attachment')
      ->fields([
        'message_id' => (int) $message->id(),
        'attachment_id' => $attachmentId,
        'ordering' => 0,
        'attached_at' => 1700000000,
        'attached_by' => 1,
      ])
      ->execute();

    $user = User::create([
      'name' => 'ratelimit-user',
      'mail' => 'ratelimit@example.com',
      'status' => 1,
    ]);
    $user->save();

    $first = $this->attachmentManager->resolveDownload($attachmentId, $user, '127.0.0.1');
    $second = $this->attachmentManager->resolveDownload($attachmentId, $user, '127.0.0.1');

    $this->assertSame(200, (int) $first['status']);
    $this->assertSame(429, (int) $second['status']);
  }

}
