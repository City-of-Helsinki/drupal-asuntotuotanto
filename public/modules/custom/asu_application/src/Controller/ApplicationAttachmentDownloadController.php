<?php

declare(strict_types=1);

namespace Drupal\asu_application\Controller;

use Drupal\asu_application\ApplicationAttachmentManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serves protected application attachments.
 */
final class ApplicationAttachmentDownloadController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly ApplicationAttachmentManager $attachmentManager,
    private readonly AccountProxyInterface $currentUserAccount,
    private readonly RequestStack $requestStack,
    private readonly FileSystemInterface $fileSystem,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('asu_application.attachment_manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('file_system'),
    );
  }

  /**
   * Deep-link endpoint used in email notifications.
   */
  public function open(int $attachment_id) {
    if (!$this->currentUserAccount->isAuthenticated()) {
      $destination = '/user/application/attachments/' . $attachment_id . '/open';
      $loginUrl = '/user/login?destination=' . rawurlencode($destination);
      return new RedirectResponse($loginUrl, 302);
    }

    return $this->buildFileResponse($attachment_id, TRUE);
  }

  /**
   * Download endpoint with strict ACL checks.
   */
  public function download(int $attachment_id) {
    return $this->buildFileResponse($attachment_id, FALSE);
  }

  /**
   * Resolves and streams an attachment with requested disposition preference.
   */
  private function buildFileResponse(int $attachment_id, bool $preferInline) {
    if (!$this->currentUserAccount->isAuthenticated()) {
      $this->getLogger('asu_application')->warning('attachment_download_denied', [
        'event' => 'attachment_download_denied',
        'attachment_id' => $attachment_id,
        'uid' => 0,
        'reason' => 'unauthenticated',
      ]);
      return new JsonResponse(['error' => 'unauthorized', 'message' => 'Authentication required.'], 401);
    }

    $request = $this->requestStack->getCurrentRequest();
    $ip = $request ? ($request->getClientIp() ?? 'unknown') : 'unknown';
    $identifier = (string) $this->currentUserAccount->id() . ':' . $ip;

    $resolved = $this->attachmentManager->resolveDownload($attachment_id, $this->currentUserAccount, $identifier);
    $status = (int) ($resolved['status'] ?? 500);

    if ($status !== 200) {
      if ($status === 403) {
        return new JsonResponse(['error' => 'forbidden', 'message' => 'Access denied.'], 403);
      }
      if ($status === 404) {
        return new JsonResponse(['error' => 'not_found', 'message' => 'Attachment not found.'], 404);
      }
      if ($status === 429) {
        return new JsonResponse(['error' => 'rate_limited', 'message' => 'Too many download attempts.'], 429);
      }

      return new JsonResponse(['error' => 'download_failed', 'message' => 'Attachment download failed.'], 500);
    }

    $file = $resolved['file'];
    $uri = (string) $file->getFileUri();
    $realPath = $this->fileSystem->realpath($uri);

    if ($realPath === FALSE || !is_file($realPath)) {
      return new JsonResponse(['error' => 'not_found', 'message' => 'Attachment not found.'], 404);
    }

    $displayName = $this->sanitizeFilename((string) ($resolved['display_name'] ?? $file->getFilename()));
    $mimeType = (string) ($resolved['mime_type'] ?? $file->getMimeType() ?: 'application/octet-stream');
    $disposition = $preferInline && $this->supportsInlinePreview($mimeType)
      ? ResponseHeaderBag::DISPOSITION_INLINE
      : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

    $response = new BinaryFileResponse($realPath, 200);
    $response->headers->set('Content-Type', $mimeType);
    $response->setContentDisposition(
      $disposition,
      $displayName,
      $displayName,
    );

    return $response;
  }

  /**
   * Determines whether attachment type can be safely previewed inline.
   */
  private function supportsInlinePreview(string $mimeType): bool {
    $mimeType = strtolower(trim($mimeType));

    return in_array($mimeType, [
      'application/pdf',
      'image/jpeg',
      'image/png',
      'text/plain',
    ], TRUE);
  }

  /**
   * Sanitizes filename for Content-Disposition.
   */
  private function sanitizeFilename(string $name): string {
    $trimmed = trim($name);
    if ($trimmed === '') {
      return 'attachment.bin';
    }

    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $trimmed) ?? '';
    $safe = ltrim($safe, '.');

    if ($safe === '') {
      return 'attachment.bin';
    }

    return $safe;
  }

}
