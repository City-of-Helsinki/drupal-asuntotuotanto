<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Controller\ApplicationAttachmentUploadController;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests protected attachment upload endpoint.
 *
 * @group asu_application
 */
final class ApplicationAttachmentUploadControllerTest extends KernelTestBase {

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
    'asu_api',
    'asu_application',
  ];

  /**
   * Created application entity for upload tests.
   */
  private Application $application;

  /**
   * Allowed sales user.
   */
  private User $salesAllowed;

  /**
   * Customer account owning the test application.
   */
  private User $customerOwner;

  /**
   * Sales user without application access.
   */
  private User $salesDenied;

  /**
   * Service account authorized via REST permission.
   */
  private AccountInterface $serviceClientAccount;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['node']);
    $this->installConfig(['file']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('application_type');
    $this->installSchema('system', ['sequences']);

    ApplicationType::create(
          [
            'id' => 'hitas',
            'label' => 'Hitas',
          ]
      )->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $this->installEntitySchema('asu_application');
    $this->installEntitySchema('asu_application_message');

    if (!Role::load('customer')) {
      Role::create(['id' => 'customer', 'label' => 'Customer'])->save();
    }
    if (!Role::load('sales')) {
      Role::create(['id' => 'sales', 'label' => 'Sales'])->save();
    }
    if (!Role::load('upload_admin')) {
      Role::create(['id' => 'upload_admin', 'label' => 'Upload Admin'])
        ->grantPermission('administer applications')
        ->save();
    }
    $restClientRole = Role::load('rest_client');
    if (!$restClientRole) {
      $restClientRole = Role::create(['id' => 'rest_client', 'label' => 'REST Client']);
    }
    if (!$restClientRole->hasPermission('restful post asu_application_messages')) {
      $restClientRole->grantPermission('restful post asu_application_messages');
    }
    $restClientRole->save();

    $owner = User::create(
          [
            'name' => 'upload-owner',
            'mail' => 'upload-owner@example.com',
            'status' => 1,
          ]
      );
    $owner->addRole('customer');
    $owner->save();
    $this->customerOwner = $owner;

    $this->salesAllowed = User::create(
          [
            'name' => 'sales-allowed',
            'mail' => 'sales-allowed@example.com',
            'status' => 1,
          ]
      );
    $this->salesAllowed->addRole('sales');
    $this->salesAllowed->addRole('upload_admin');
    $this->salesAllowed->save();

    $this->salesDenied = User::create(
          [
            'name' => 'sales-denied',
            'mail' => 'sales-denied@example.com',
            'status' => 1,
          ]
      );
    $this->salesDenied->addRole('sales');
    $this->salesDenied->save();

    $this->serviceClientAccount = new class() implements AccountInterface {

      /**
       * Returns fixed service account id.
       */
      public function id() {
        return 9001;
      }

      /**
       * Returns service account roles.
       */
      public function getRoles($exclude_locked_roles = FALSE) {
        return $exclude_locked_roles ? ['rest_client'] : ['authenticated', 'rest_client'];
      }

      /**
       * Grants only REST post permission.
       */
      public function hasPermission(string $permission) {
        return $permission === 'restful post asu_application_messages';
      }

      /**
       * Indicates authenticated account.
       */
      public function isAuthenticated() {
        return TRUE;
      }

      /**
       * Indicates non-anonymous account.
       */
      public function isAnonymous() {
        return FALSE;
      }

      /**
       * Returns preferred content language.
       */
      public function getPreferredLangcode($fallback_to_default = TRUE) {
        return 'fi';
      }

      /**
       * Returns preferred admin language.
       */
      public function getPreferredAdminLangcode($fallback_to_default = TRUE) {
        return 'fi';
      }

      /**
       * Returns account machine name.
       */
      public function getAccountName() {
        return 'service-client';
      }

      /**
       * Returns display name.
       */
      public function getDisplayName() {
        return 'Service Client';
      }

      /**
       * Returns no email for service account.
       */
      public function getEmail() {
        return NULL;
      }

      /**
       * Returns fixed timezone.
       */
      public function getTimeZone() {
        return 'UTC';
      }

      /**
       * Returns fixed access timestamp.
       */
      public function getLastAccessedTime() {
        return 0;
      }

    };

    $this->container->get('current_user')->setAccount($owner);
    $request = Request::create('/application/add/hitas/42', 'GET');
    $current = $this->container->get('request_stack')->getCurrentRequest();
    if ($current && $current->hasSession()) {
      $request->setSession($current->getSession());
    }
    $this->container->get('request_stack')->push($request);

    $this->application = Application::create(
          [
            'bundle' => 'hitas',
            'project_id' => 42,
            'status' => 1,
          ]
      );
    $this->application->save();

    // Ensure attachment tables and settings are present.
    $this->container->get('module_handler')->loadInclude('asu_application', 'install');
    asu_application_update_9019();
    asu_application_update_9020();
    asu_application_update_9021();

    $privatePath = sys_get_temp_dir() . '/asu-private-' . uniqid('', TRUE);
    if (!is_dir($privatePath)) {
      mkdir($privatePath, 0777, TRUE);
    }
    $settings = Settings::getAll();
    $settings['file_private_path'] = $privatePath;
    new Settings($settings);
    if (!in_array('private', stream_get_wrappers(), TRUE)) {
      stream_wrapper_register('private', PrivateStream::class);
    }
    $streamWrapperManager = $this->container->get('stream_wrapper_manager');
    $streamWrapperManager->registerWrapper('private', PrivateStream::class, PrivateStream::getType());

    // Kernel fileinfo can report variant MIME labels for fixture files.
    $this->config('asu_application.settings')
      ->set(
              'attachments.allowed_mime_types', [
                'application/pdf',
                'application/x-pdf',
                'application/octet-stream',
              ]
          )
      ->set('attachments.max_file_size_bytes', 20971520)
      ->set('attachments.rate_limit.upload_per_user', 20)
      ->set('attachments.rate_limit.window_seconds', 60)
      ->save();
  }

  /**
   * Upload succeeds for authenticated sales user with application access.
   */
  public function testSuccessUpload(): void {
    $response = $this->uploadAs($this->salesAllowed, 'ok.pdf', 'application/pdf', $this->pdfFixture(64));

    $this->assertSame(201, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertIsArray($payload);

    $this->assertGreaterThan(0, (int) $payload['file_id']);
    $this->assertGreaterThan(0, (int) $payload['attachment_id']);
    $this->assertSame('ok.pdf', (string) $payload['display_name']);
    $this->assertSame('application/pdf', (string) $payload['mime_type']);
    $this->assertGreaterThan(0, (int) $payload['size_bytes']);
  }

  /**
   * Service client can upload when sales context fields are provided.
   */
  public function testServiceClientSalesContextUploadSuccess(): void {
    $response = $this->uploadAs(
          $this->serviceClientAccount,
          'service-ok.pdf',
          'application/pdf',
          $this->pdfFixture(64),
          [
            'sender_role' => 'sales',
            'viewer_role' => 'sales',
            'sales_shared' => '1',
          ],
      );

    $this->assertSame(201, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertIsArray($payload);
    $this->assertGreaterThan(0, (int) $payload['attachment_id']);
  }

  /**
   * Generic octet-stream upload is accepted for known safe extension.
   */
  public function testOctetStreamPdfExtensionIsAccepted(): void {
    $this->config('asu_application.settings')
      ->set(
              'attachments.allowed_mime_types', [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
              ]
          )
      ->save();

    $response = $this->uploadAs(
          $this->serviceClientAccount,
          'from-django.pdf',
          'application/octet-stream',
          $this->pdfFixture(96),
          [
            'sender_role' => 'sales',
            'viewer_role' => 'sales',
            'sales_shared' => '1',
          ],
      );

    $this->assertSame(201, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('application/pdf', (string) $payload['mime_type']);
  }

  /**
   * Generic octet-stream upload fails when signature mismatches extension.
   */
  public function testOctetStreamExtensionMismatchReturns422(): void {
    $this->config('asu_application.settings')
      ->set(
              'attachments.allowed_mime_types', [
                'application/pdf',
                'text/plain',
              ]
          )
      ->save();

    $response = $this->uploadAs(
          $this->serviceClientAccount,
          'spoofed.pdf',
          'application/octet-stream',
          "not-a-pdf\n",
          [
            'sender_role' => 'sales',
            'viewer_role' => 'sales',
            'sales_shared' => '1',
          ],
      );

    $this->assertSame(422, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('invalid_file', $payload['error']);
    $this->assertSame('mime_extension_mismatch', $payload['code']);
  }

  /**
   * Invalid mime type returns 422 invalid_file.
   */
  public function testInvalidMimeReturns422(): void {
    $this->config('asu_application.settings')
      ->set('attachments.allowed_mime_types', ['image/png'])
      ->save();

    $response = $this->uploadAs($this->salesAllowed, 'bad.pdf', 'application/pdf', $this->pdfFixture(64));

    $this->assertSame(422, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('invalid_file', $payload['error']);
    $this->assertSame('mime_not_allowed', $payload['code']);
  }

  /**
   * Too large upload returns 422 invalid_file.
   */
  public function testTooLargeReturns422(): void {
    $this->config('asu_application.settings')
      ->set(
              'attachments.allowed_mime_types', [
                'application/pdf',
                'application/x-pdf',
                'application/octet-stream',
              ]
          )
      ->set('attachments.max_file_size_bytes', 2)
      ->save();

    $response = $this->uploadAs($this->salesAllowed, 'big.pdf', 'application/pdf', $this->pdfFixture(128));

    $this->assertSame(422, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('invalid_file', $payload['error']);
    $this->assertSame('size_exceeded', $payload['code']);
  }

  /**
   * Sales user without application access receives 403.
   */
  public function testNoAccessReturns403(): void {
    $response = $this->uploadAs($this->salesDenied, 'denied.pdf', 'application/pdf', $this->pdfFixture(64));

    $this->assertSame(403, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('forbidden', $payload['error']);
  }

  /**
   * Customer sender role is forbidden for uploads.
   */
  public function testCustomerUploadForbidden(): void {
    $response = $this->uploadAs(
          $this->customerOwner,
          'customer.pdf',
          'application/pdf',
          $this->pdfFixture(64),
          [
            'sender_role' => 'customer',
          ],
      );

    $this->assertSame(403, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('forbidden', $payload['error']);
    $this->assertSame('sender_role_forbidden', $payload['code']);
  }

  /**
   * Sales_shared and viewer_role participate in ACL decisions.
   */
  public function testSalesSharedAndViewerRoleAffectAcl(): void {
    $roleMismatch = $this->uploadAs(
          $this->serviceClientAccount,
          'role-mismatch.pdf',
          'application/pdf',
          $this->pdfFixture(64),
          [
            'sender_role' => 'sales',
            'viewer_role' => 'customer',
            'sales_shared' => '1',
          ],
      );
    $this->assertSame(403, $roleMismatch->getStatusCode());
    $rolePayload = json_decode((string) $roleMismatch->getContent(), TRUE);
    $this->assertSame('viewer_role_mismatch', $rolePayload['code']);

    $missingShared = $this->uploadAs(
          $this->serviceClientAccount,
          'shared-required.pdf',
          'application/pdf',
          $this->pdfFixture(64),
          [
            'sender_role' => 'sales',
            'viewer_role' => 'sales',
            'sales_shared' => '0',
          ],
      );
    $this->assertSame(403, $missingShared->getStatusCode());
    $sharedPayload = json_decode((string) $missingShared->getContent(), TRUE);
    $this->assertSame('sales_shared_required', $sharedPayload['code']);
  }

  /**
   * Upload rate limiting returns 429 on second request.
   */
  public function testRateLimitReturns429(): void {
    $this->config('asu_application.settings')
      ->set('attachments.rate_limit.upload_per_user', 1)
      ->set('attachments.rate_limit.window_seconds', 3600)
      ->save();

    $first = $this->uploadAs($this->salesAllowed, 'first.pdf', 'application/pdf', $this->pdfFixture(64));
    $second = $this->uploadAs($this->salesAllowed, 'second.pdf', 'application/pdf', $this->pdfFixture(64));

    $this->assertSame(201, $first->getStatusCode());
    $this->assertSame(429, $second->getStatusCode());
    $payload = json_decode((string) $second->getContent(), TRUE);
    $this->assertSame('rate_limited', $payload['error']);
  }

  /**
   * Anonymous upload receives 401.
   */
  public function testAnonymousReturns401(): void {
    $request = $this->buildUploadRequest('anon.pdf', 'application/pdf', $this->pdfFixture(64));
    $this->container->get('request_stack')->push($request);
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());

    $response = $this->controller()->upload($request);

    $this->assertSame(401, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('unauthorized', $payload['error']);
  }

  /**
   * Missing file returns invalid_upload with missing_file code.
   */
  public function testMissingFileReturns422(): void {
    $request = $this->buildUploadRequest(
          'nofile.pdf',
          'application/pdf',
          $this->pdfFixture(64),
          [
            'sender_role' => 'sales',
            'viewer_role' => 'sales',
            'sales_shared' => '1',
          ],
          FALSE,
      );
    $this->container->get('request_stack')->push($request);
    \Drupal::currentUser()->setAccount($this->serviceClientAccount);

    $response = $this->controller()->upload($request);

    $this->assertSame(422, $response->getStatusCode());
    $payload = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('invalid_upload', $payload['error']);
    $this->assertSame('missing_file', $payload['code']);
  }

  /**
   * Executes upload request as specific user.
   */
  private function uploadAs(AccountInterface $user, string $filename, string $mimeType, string $content, array $extraFields = []) {
    $request = $this->buildUploadRequest($filename, $mimeType, $content, $extraFields);
    $this->container->get('request_stack')->push($request);
    \Drupal::currentUser()->setAccount($user);

    return $this->controller()->upload($request);
  }

  /**
   * Builds multipart upload request with temporary file.
   */
  private function buildUploadRequest(
    string $filename,
    string $mimeType,
    string $content,
    array $extraFields = [],
    bool $includeFile = TRUE,
  ): Request {
    $tmpPath = tempnam(sys_get_temp_dir(), 'asu-upload-');
    file_put_contents($tmpPath, $content);

    $uploaded = new UploadedFile(
          $tmpPath,
          $filename,
          $mimeType,
          NULL,
          TRUE,
      );

    $postData = [
      'application_id' => (int) $this->application->id(),
      'display_name' => $filename,
    ] + $extraFields;

    $files = $includeFile ? ['file' => $uploaded] : [];

    $request = Request::create('/user/application/attachments/upload', 'POST', $postData, [], $files);

    $current = $this->container->get('request_stack')->getCurrentRequest();
    if ($current && $current->hasSession()) {
      $request->setSession($current->getSession());
    }

    return $request;
  }

  /**
   * Resolves controller from container.
   */
  private function controller(): ApplicationAttachmentUploadController {
    return $this->container->get('class_resolver')->getInstanceFromDefinition(ApplicationAttachmentUploadController::class);
  }

  /**
   * Creates deterministic PDF-like content for MIME detection in tests.
   */
  private function pdfFixture(int $payloadBytes): string {
    return "%PDF-1.4\n" . str_repeat('A', $payloadBytes);
  }

}
