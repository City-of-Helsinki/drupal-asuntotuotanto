<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Controller\ApplicationAttachmentDownloadController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests protected attachment download behavior.
 *
 * @group asu_application
 */
final class ApplicationAttachmentDownloadControllerTest extends KernelTestBase {

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
    asu_application_update_9019();
  }

  /**
   * Customer can download attachment linked to own message thread.
   */
  public function testCustomerCanDownloadOwnAttachment(): void {
    $customer = $this->createUser('customer1@example.com');
    $messageId = $this->createMessageForRecipient('customer1@example.com');
    $attachmentId = $this->createAttachment('offer.pdf', 'application/pdf');
    $this->linkAttachmentToMessage($messageId, $attachmentId);

    $response = $this->requestDownload($customer, $attachmentId);

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('application/pdf', (string) $response->headers->get('Content-Type'));
    $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
  }

  /**
   * Open endpoint serves previewable files inline for authenticated users.
   */
  public function testCustomerCanOpenAttachmentInline(): void {
    $customer = $this->createUser('customer-open@example.com');
    $messageId = $this->createMessageForRecipient('customer-open@example.com');
    $attachmentId = $this->createAttachment('offer-open.pdf', 'application/pdf');
    $this->linkAttachmentToMessage($messageId, $attachmentId);

    $response = $this->requestOpen($customer, $attachmentId);

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
  }

  /**
   * Anonymous users cannot access the strict download endpoint.
   */
  public function testAnonymousCannotDownload(): void {
    $messageId = $this->createMessageForRecipient('customer2@example.com');
    $attachmentId = $this->createAttachment('offer.txt', 'text/plain');
    $this->linkAttachmentToMessage($messageId, $attachmentId);

    \Drupal::currentUser()->setAccount(new \Drupal\Core\Session\AnonymousUserSession());
    $controller = $this->controller();
    $response = $controller->download($attachmentId);

    $this->assertSame(401, $response->getStatusCode());
  }

  /**
   * Other authenticated customer cannot open foreign attachment.
   */
  public function testForeignCustomerCannotDownload(): void {
    $owner = $this->createUser('owner@example.com');
    $other = $this->createUser('other@example.com');

    $messageId = $this->createMessageForRecipient('owner@example.com');
    $attachmentId = $this->createAttachment('private.pdf', 'application/pdf');
    $this->linkAttachmentToMessage($messageId, $attachmentId);

    $this->assertInstanceOf(BinaryFileResponse::class, $this->requestDownload($owner, $attachmentId));

    $response = $this->requestDownload($other, $attachmentId);
    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Revoked attachments are hidden from download.
   */
  public function testRevokedAttachmentCannotBeDownloaded(): void {
    $customer = $this->createUser('revoked@example.com');
    $messageId = $this->createMessageForRecipient('revoked@example.com');
    $attachmentId = $this->createAttachment('revoked.pdf', 'application/pdf', 1, 1, 1, 0);
    $this->linkAttachmentToMessage($messageId, $attachmentId);

    $response = $this->requestDownload($customer, $attachmentId);
    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * Archived attachments are hidden from download.
   */
  public function testArchivedAttachmentCannotBeDownloaded(): void {
    $customer = $this->createUser('archived@example.com');
    $messageId = $this->createMessageForRecipient('archived@example.com');
    $attachmentId = $this->createAttachment('archived.pdf', 'application/pdf', 1, 1, 0, 1);
    $this->linkAttachmentToMessage($messageId, $attachmentId);

    $response = $this->requestDownload($customer, $attachmentId);
    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * Same attachment can be reused across different customers safely.
   */
  public function testAttachmentReuseAcrossThreads(): void {
    $customerA = $this->createUser('reuse-a@example.com');
    $customerB = $this->createUser('reuse-b@example.com');

    $attachmentId = $this->createAttachment('template.pdf', 'application/pdf');

    $messageA = $this->createMessageForRecipient('reuse-a@example.com');
    $messageB = $this->createMessageForRecipient('reuse-b@example.com');
    $this->linkAttachmentToMessage($messageA, $attachmentId);
    $this->linkAttachmentToMessage($messageB, $attachmentId);

    $this->assertSame(200, $this->requestDownload($customerA, $attachmentId)->getStatusCode());
    $this->assertSame(200, $this->requestDownload($customerB, $attachmentId)->getStatusCode());
  }

  /**
   * Email deep-link redirects anonymous users to login with destination.
   */
  public function testOpenRouteRedirectsAnonymousToLogin(): void {
    \Drupal::currentUser()->setAccount(new \Drupal\Core\Session\AnonymousUserSession());

    $controller = $this->controller();
    $response = $controller->open(123);

    $this->assertSame(302, $response->getStatusCode());
    $this->assertStringContainsString('/user/login?destination=%2Fuser%2Fapplication%2Fattachments%2F123%2Fopen', (string) $response->headers->get('Location'));
  }

  /**
   * Creates a user account.
   */
  private function createUser(string $email): User {
    $user = User::create([
      'name' => $email,
      'mail' => $email,
      'status' => 1,
    ]);
    $user->save();

    return $user;
  }

  /**
   * Creates application message row.
   */
  private function createMessageForRecipient(string $recipientEmail): int {
    $storage = $this->container->get('entity_type.manager')->getStorage('asu_application_message');
    $message = $storage->create([
      'application_id' => 9001,
      'project_id' => 5001,
      'sender_role' => 'sales',
      'recipient_mail' => $recipientEmail,
      'body' => 'Attachment test message',
      'created' => 1700000000,
    ]);
    $message->save();

    return (int) $message->id();
  }

  /**
   * Creates attachment metadata row and backing file entity.
   */
  private function createAttachment(
    string $filename,
    string $mimeType,
    int $active = 1,
    int $customerVisible = 1,
    int $revoked = 0,
    int $archived = 0,
  ): int {
    $fileSystem = $this->container->get('file_system');
    $uri = $fileSystem->saveData('file-content', 'temporary://' . $filename, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);

    $file = \Drupal\file\Entity\File::create([
      'uri' => $uri,
      'filename' => $filename,
      'filemime' => $mimeType,
      'status' => 1,
    ]);
    $file->save();

    $insertId = (int) $this->container->get('database')->insert('asu_application_attachment')
      ->fields([
        'display_name' => $filename,
        'mime_type' => $mimeType,
        'size_bytes' => 1024,
        'storage_uri' => (string) $uri,
        'file_id' => (int) $file->id(),
        'media_id' => 0,
        'active' => $active,
        'customer_visible' => $customerVisible,
        'revoked' => $revoked,
        'archived' => $archived,
        'created' => 1700000000,
        'created_by' => 1,
      ])
      ->execute();

    return $insertId;
  }

  /**
   * Links attachment to message.
   */
  private function linkAttachmentToMessage(int $messageId, int $attachmentId): void {
    $this->container->get('database')->insert('asu_application_message_attachment')
      ->fields([
        'message_id' => $messageId,
        'attachment_id' => $attachmentId,
        'ordering' => 0,
        'attached_at' => 1700000000,
        'attached_by' => 1,
      ])
      ->execute();
  }

  /**
   * Executes a download request as a user.
   */
  private function requestDownload(User $user, int $attachmentId) {
    $request = Request::create('/user/application/attachments/' . $attachmentId . '/download', 'GET');
      $current = $this->container->get('request_stack')->getCurrentRequest();
      if ($current && $current->hasSession()) {
        $request->setSession($current->getSession());
      }
    $this->container->get('request_stack')->push($request);

    \Drupal::currentUser()->setAccount($user);
    $controller = $this->controller();

    return $controller->download($attachmentId);
  }

  /**
   * Executes an open request as a user.
   */
  private function requestOpen(User $user, int $attachmentId) {
    $request = Request::create('/user/application/attachments/' . $attachmentId . '/open', 'GET');
    $current = $this->container->get('request_stack')->getCurrentRequest();
    if ($current && $current->hasSession()) {
      $request->setSession($current->getSession());
    }
    $this->container->get('request_stack')->push($request);

    \Drupal::currentUser()->setAccount($user);
    $controller = $this->controller();

    return $controller->open($attachmentId);
  }

  /**
   * Returns controller instance from container.
   */
  private function controller(): ApplicationAttachmentDownloadController {
    return $this->container->get('class_resolver')->getInstanceFromDefinition(ApplicationAttachmentDownloadController::class);
  }

}
