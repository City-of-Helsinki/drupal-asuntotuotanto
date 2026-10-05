<?php

declare(strict_types=1);

namespace Drupal\asu_application\Controller;

use Drupal\asu_application\Service\ApplicationPaymentSyncService;
use Drupal\Core\Controller\ControllerBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives payment sync payloads from external systems.
 */
final class ApplicationPaymentWebhookController extends ControllerBase {

  public function __construct(
    private readonly ApplicationPaymentSyncService $paymentSync,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('asu_application.payment_sync'),
      $container->get('logger.channel.asu_application'),
    );
  }

  /**
   * Sync endpoint for application payment data.
   */
  public function sync(Request $request): JsonResponse {
    $correlationId = $this->resolveCorrelationId($request);

    try {
      $decoded = json_decode((string) $request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
      $records = $this->extractRecords($decoded);
      $summary = $this->paymentSync->upsertPayments($records, $correlationId);

      return new JsonResponse([
        'success' => TRUE,
        'correlation_id' => $correlationId,
        'summary' => $summary,
      ], 200);
    }
    catch (\JsonException $exception) {
      $this->logger->error('Payment sync JSON decode failed. Correlation id: @correlation_id. Error: @error', [
        '@correlation_id' => $correlationId,
        '@error' => $exception->getMessage(),
      ]);

      return new JsonResponse([
        'success' => FALSE,
        'correlation_id' => $correlationId,
        'message' => 'Invalid JSON payload.',
      ], 400);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Payment sync failed. Correlation id: @correlation_id. Error: @error', [
        '@correlation_id' => $correlationId,
        '@error' => $exception->getMessage(),
      ]);

      return new JsonResponse([
        'success' => FALSE,
        'correlation_id' => $correlationId,
        'message' => 'Payment sync failed.',
      ], 500);
    }
  }

  /**
   * Normalize payload shape to a flat list of records.
   */
  private function extractRecords(mixed $decoded): array {
    if (!is_array($decoded)) {
      return [];
    }

    if (isset($decoded['payments']) && is_array($decoded['payments'])) {
      return $decoded['payments'];
    }

    if (array_is_list($decoded)) {
      return $decoded;
    }

    return [$decoded];
  }

  /**
   * Resolve correlation id from headers or payload metadata.
   */
  private function resolveCorrelationId(Request $request): string {
    $header = trim((string) (
      $request->headers->get('X-Correlation-Id')
      ?? $request->headers->get('X-Request-Id')
      ?? ''
    ));

    if ($header !== '') {
      return $header;
    }

    return (string) \Drupal::service('uuid')->generate();
  }

}
