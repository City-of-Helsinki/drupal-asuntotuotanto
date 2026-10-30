<?php

declare(strict_types=1);

namespace Drupal\asu_application\Controller;

use Drupal\asu_application\ApplicationAttachmentManager;
use Drupal\asu_application\ApplicationMessageManager;
use Drupal\asu_application\Entity\Application;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles protected message attachment uploads.
 */
final class ApplicationAttachmentUploadController extends ControllerBase {

  /**
   * Constructs the upload controller.
   */
  public function __construct(
    private readonly ApplicationAttachmentManager $attachmentManager,
    private readonly ApplicationMessageManager $messageManager,
    private readonly AccountProxyInterface $currentUserAccount,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('asu_application.attachment_manager'),
      $container->get('asu_application.message_manager'),
      $container->get('current_user'),
      $container->get('logger.factory')->get('asu_application'),
    );
  }

  /**
   * Uploads an attachment file to protected Drupal storage.
   */
  public function upload(Request $request): JsonResponse {
    if (!$this->currentUserAccount->isAuthenticated()) {
      $this->logUploadDeny('unauthenticated', 0, [
        'sender_role' => (string) ($request->request->get('sender_role') ?? ''),
        'viewer_role' => (string) ($request->request->get('viewer_role') ?? ''),
      ]);
      return new JsonResponse([
        'error' => 'unauthorized',
        'code' => 'unauthenticated',
        'message' => 'Authentication required.',
      ], 401);
    }

    $uid = (int) $this->currentUserAccount->id();
    $senderRole = trim((string) $request->request->get('sender_role', 'sales'));
    if (!in_array($senderRole, ['sales', 'customer'], TRUE)) {
      $this->logUploadDeny('invalid_sender_role', $uid, ['sender_role' => $senderRole]);
      return new JsonResponse([
        'error' => 'invalid_upload',
        'code' => 'invalid_sender_role',
        'message' => 'sender_role must be one of: sales, customer.',
      ], 422);
    }

    if ($senderRole !== 'sales') {
      $this->logUploadDeny('sender_role_forbidden', $uid, ['sender_role' => $senderRole]);
      return new JsonResponse([
        'error' => 'forbidden',
        'code' => 'sender_role_forbidden',
        'message' => 'Only sales sender_role can upload attachments.',
      ], 403);
    }

    $resolvedViewerRole = $this->messageManager->resolveViewerRole($uid);
    $viewerRole = trim((string) $request->request->get('viewer_role', $resolvedViewerRole));
    $salesShared = $request->request->has('sales_shared')
      ? $request->request->getBoolean('sales_shared')
      : ($senderRole === 'sales' && $viewerRole === 'sales');

    if ($senderRole === 'sales' && $viewerRole !== 'sales') {
      $this->logUploadDeny('viewer_role_mismatch', $uid, [
        'sender_role' => $senderRole,
        'viewer_role' => $viewerRole,
      ]);
      return new JsonResponse([
        'error' => 'forbidden',
        'code' => 'viewer_role_mismatch',
        'message' => 'Sales attachment upload requires viewer_role=sales.',
      ], 403);
    }

    if ($senderRole === 'sales' && !$salesShared) {
      $this->logUploadDeny('sales_shared_required', $uid, [
        'sender_role' => $senderRole,
        'viewer_role' => $viewerRole,
      ]);
      return new JsonResponse([
        'error' => 'forbidden',
        'code' => 'sales_shared_required',
        'message' => 'sales_shared=1 is required for sales attachment uploads.',
      ], 403);
    }

    $applicationId = (int) $request->request->get('application_id', 0);
    if ($applicationId <= 0) {
      $this->logUploadDeny('invalid_application_id', $uid, [
        'sender_role' => $senderRole,
        'viewer_role' => $viewerRole,
      ]);
      return new JsonResponse([
        'error' => 'invalid_upload',
        'code' => 'invalid_application_id',
        'message' => 'application_id is required and must be a positive integer.',
      ], 422);
    }

    $application = Application::load($applicationId);
    if (!$application) {
      $this->logUploadDeny('application_not_found', $uid, ['application_id' => $applicationId]);
      return new JsonResponse([
        'error' => 'not_found',
        'code' => 'application_not_found',
        'message' => 'Application not found.',
      ], 404);
    }

    if (!$this->canUploadForApplication($application, $senderRole)) {
      $this->logUploadDeny('application_access_denied', $uid, [
        'application_id' => $applicationId,
        'sender_role' => $senderRole,
        'viewer_role' => $viewerRole,
      ]);
      return new JsonResponse([
        'error' => 'forbidden',
        'code' => 'application_access_denied',
        'message' => 'Access denied for this application.',
      ], 403);
    }

    $uploadedFile = $request->files->get('file');
    if (!$uploadedFile instanceof UploadedFile) {
      $this->logUploadDeny('missing_file', $uid, ['application_id' => $applicationId]);
      return new JsonResponse([
        'error' => 'invalid_upload',
        'code' => 'missing_file',
        'message' => 'Multipart field "file" is required.',
      ], 422);
    }

    $displayName = trim((string) $request->request->get('display_name', ''));
    $identifier = (string) $uid . ':' . ($request->getClientIp() ?? 'unknown');

    $result = $this->attachmentManager->uploadAttachment(
      $uploadedFile,
      $uid,
      $identifier,
      $displayName,
      [
        'application_id' => $applicationId,
        'sender_role' => $senderRole,
        'viewer_role' => $viewerRole,
        'sales_shared' => $salesShared ? 1 : 0,
      ],
    );

    $status = (int) ($result['status'] ?? 500);
    if ($status !== 201) {
      if ($status === 429) {
        return new JsonResponse([
          'error' => 'rate_limited',
          'code' => (string) ($result['code'] ?? 'upload_rate_limited'),
          'message' => (string) ($result['message'] ?? 'Too many upload attempts.'),
        ], 429);
      }

      if ($status === 503) {
        return new JsonResponse([
          'error' => 'temporary_failure',
          'code' => (string) ($result['code'] ?? 'storage_write_failed'),
          'message' => (string) ($result['message'] ?? 'Temporary storage failure.'),
        ], 503);
      }

      return new JsonResponse([
        'error' => (string) ($result['error'] ?? 'invalid_upload'),
        'code' => (string) ($result['code'] ?? 'upload_validation_failed'),
        'message' => (string) ($result['message'] ?? 'Upload validation failed.'),
      ], 422);
    }

    return new JsonResponse([
      'file_id' => (int) $result['file_id'],
      'attachment_id' => (int) ($result['attachment_id'] ?? 0),
      'display_name' => (string) $result['display_name'],
      'mime_type' => (string) $result['mime_type'],
      'size_bytes' => (int) $result['size_bytes'],
    ], 201);
  }

  /**
   * Verifies upload access aligned with message thread write ACL.
   */
  private function canUploadForApplication(Application $application, string $senderRole): bool {
    return $application->access('update', $this->currentUserAccount, TRUE)->isAllowed()
      || $this->currentUserAccount->hasPermission('restful post asu_application_messages');
  }

  /**
   * Logs denied upload attempts without leaking sensitive request content.
   *
   * @param array<string, mixed> $context
   *   Additional context fields.
   */
  private function logUploadDeny(string $reason, int $uid, array $context = []): void {
    $this->logger->warning('attachment_upload_acl_denied', $context + [
      'event' => 'attachment_upload_acl_denied',
      'reason' => $reason,
      'uid' => $uid,
    ]);
  }

}
