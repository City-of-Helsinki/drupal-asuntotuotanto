<?php

declare(strict_types=1);

namespace Drupal\asu_application\Controller;

use Drupal\asu_application\ApplicationMessageManager;
use Drupal\asu_application\Applications;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Session\AccountInterface;

/**
 * Returns unread message counts for current user's applications.
 */
final class ApplicationUnreadController extends ControllerBase {

  /**
   * Constructs controller.
   */
  public function __construct(
    private readonly ApplicationMessageManager $messageManager,
    private readonly TimeInterface $time,
    private readonly Connection $database,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('asu_application.message_manager'),
      $container->get('datetime.time'),
      $container->get('database'),
    );
  }

  /**
   * Returns unread message counts per application.
   */
  public function getUnreadCounts(Request $request): JsonResponse {
    $account = $this->currentUser();
    if (!$account->isAuthenticated()) {
      return $this->buildNoStoreResponse([
        'counts' => [],
        'total' => 0,
        'error' => 'unauthenticated',
        'message' => 'Authentication is required.',
      ], 403);
    }

    try {
      $viewerRole = NULL;
      $salesShared = $request->query->getBoolean('sales_shared');
      if ($salesShared) {
        if (!$this->canUseSharedSales($account)) {
          return $this->buildForbiddenResponse('Missing permission: view shared sales unread counts.', [
            'counts' => [],
            'total' => 0,
          ]);
        }

        $viewerRole = 'sales';
      }

      $application_ids = $this->extractApplicationIds($request);
      if ($application_ids === []) {
        if ($salesShared) {
          $application_ids = $this->getSalesSharedApplicationIds();
        }
        else {
          $application_pairs = Applications::applicationsByUser((string) $account->id())
            ->getApplicationsProjectPairs();
          $application_ids = array_map(static fn(array $pair): int => (int) ($pair['application_id'] ?? 0), $application_pairs);
          $application_ids = array_values(array_filter($application_ids));
        }
      }

      $counts = $application_ids === []
        ? []
        : $this->messageManager->getUnreadCountsForApplications((int) $account->id(), $application_ids, $viewerRole);

      return $this->buildNoStoreResponse([
        'counts' => $counts,
        'total' => array_sum($counts),
      ]);
    }
    catch (\Throwable $throwable) {
      return $this->buildServerErrorResponse(
        'Unread count endpoint failed',
        $throwable,
        [
          'counts' => [],
          'total' => 0,
          'error' => 'internal_error',
          'message' => 'Unread count endpoint failed.',
        ]
      );
    }
  }

  /**
   * Returns sales inbox summary grouped by application.
   */
  public function getInboxSummary(Request $request): JsonResponse {
    $forbidden = $this->buildUnauthenticatedResponse();
    if ($forbidden !== NULL) {
      return $forbidden;
    }

    try {
      if (!$this->isSalesSharedRequest($request)) {
        return $this->buildForbiddenResponse('sales_shared=1 is required.');
      }

      if (!$this->canUseSharedSales($this->currentUser())) {
        return $this->buildForbiddenResponse('Missing permission: view shared sales unread counts.');
      }

      $applicationIds = $this->extractApplicationIds($request);
      if ($applicationIds === []) {
        $applicationIds = $this->getSalesSharedApplicationIds();
      }

      $items = $applicationIds === []
        ? []
        : $this->messageManager->getSalesInboxSummary($applicationIds);

      return $this->buildNoStoreResponse([
        'items' => $items,
        'total_unread' => array_sum(array_map(static fn(array $item): int => (int) ($item['unread_count'] ?? 0), $items)),
      ]);
    }
    catch (\Throwable $throwable) {
      return $this->buildServerErrorResponse(
        'Inbox summary endpoint failed',
        $throwable,
        [
          'error' => 'server_error',
          'message' => 'Inbox summary endpoint failed.',
        ]
      );
    }
  }

  /**
   * Marks applications as read for shared sales context.
   */
  public function markRead(Request $request): JsonResponse {
    $forbidden = $this->buildUnauthenticatedResponse();
    if ($forbidden !== NULL) {
      return $forbidden;
    }

    try {
      if (!$this->isSalesSharedRequest($request)) {
        return $this->buildForbiddenResponse('sales_shared=1 is required.');
      }

      if (!$this->canUseSharedSales($this->currentUser())) {
        return $this->buildForbiddenResponse('Missing permission: view shared sales unread counts.');
      }

      $applicationIds = $this->extractApplicationIdsFromInput($request);
      if ($applicationIds === []) {
        return $this->buildForbiddenResponse('application_id or application_ids is required.');
      }

      $timestamp = $this->time->getRequestTime();
      foreach ($applicationIds as $applicationId) {
        $this->messageManager->markThreadReadSalesShared($applicationId, $timestamp);
      }

      return $this->buildNoStoreResponse([
        'updated_application_ids' => $applicationIds,
        'updated_at' => gmdate('c', $timestamp),
      ]);
    }
    catch (\Throwable $throwable) {
      return $this->buildServerErrorResponse(
        'Mark read endpoint failed',
        $throwable,
        [
          'error' => 'server_error',
          'message' => 'Mark read endpoint failed.',
        ]
      );
    }
  }

  /**
   * Reads application ids from query string.
   *
   * Supports both formats:
   * - ?application_ids=1,2,3
   * - ?application_ids[]=1&application_ids[]=2
   *
   * @return int[]
   *   Deduplicated positive ids.
   */
  private function extractApplicationIds(Request $request): array {
    $ids = [];

    // Handle repeated query params (?application_ids[]=1&application_ids[]=2).
    $raw = $request->query->get('application_ids');
    if (is_array($raw) && $raw !== []) {
      $ids = $raw;
    }
    else {
      // Handle CSV form (?application_ids=1,2,3).
      $csv = (string) ($raw ?? '');
      if ($csv !== '') {
        $ids = explode(',', $csv);
      }
    }

    if ($ids === []) {
      return [];
    }

    $normalized = array_map(static fn($value): int => (int) trim((string) $value), $ids);
    $normalized = array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));

    return $normalized;
  }

  /**
   * Reads application ids from query/body.
   *
   * Supports these forms:
   * - application_id=123
   * - application_ids=1,2,3
   * - application_ids[]=1&application_ids[]=2
   *
   * @return int[]
   *   Deduplicated positive ids.
   */
  private function extractApplicationIdsFromInput(Request $request): array {
    $ids = $this->extractApplicationIds($request);
    if ($ids !== []) {
      return $ids;
    }

    $rawIds = $request->request->get('application_ids');
    if (is_array($rawIds) && $rawIds !== []) {
      $normalized = array_map(static fn($value): int => (int) trim((string) $value), $rawIds);
      return array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));
    }

    $csvIds = (string) ($request->request->get('application_ids') ?? '');
    if ($csvIds !== '') {
      $normalized = array_map(static fn($value): int => (int) trim((string) $value), explode(',', $csvIds));
      return array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));
    }

    $singleId = (int) $request->request->get('application_id');
    if ($singleId > 0) {
      return [$singleId];
    }

    $singleId = (int) $request->query->get('application_id');
    return $singleId > 0 ? [$singleId] : [];
  }

  /**
   * Returns application ids that have incoming customer messages.
   *
   * Used for sales shared mode when caller does not provide explicit ids.
   *
   * @return int[]
   *   Deduplicated positive ids.
   */
  private function getSalesSharedApplicationIds(): array {
    $ids = $this->database
      ->select('asu_application_message', 'm')
      ->fields('m', ['application_id'])
      ->condition('m.sender_role', 'customer')
      ->distinct()
      ->execute()
      ->fetchCol();

    $normalized = array_map(static fn($value): int => (int) $value, $ids);
    $normalized = array_values(array_unique(array_filter($normalized, static fn(int $id): bool => $id > 0)));

    return $normalized;
  }

  /**
   * Creates no-store JSON response.
   */
  private function buildNoStoreResponse(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    return $response;
  }

  /**
   * Checks whether sales_shared mode is enabled in query/body.
   */
  private function isSalesSharedRequest(Request $request): bool {
    return $request->query->getBoolean('sales_shared')
      || $request->request->getBoolean('sales_shared');
  }

  /**
   * Builds a generic forbidden response.
   */
  private function buildForbiddenResponse(string $message, array $extra = []): JsonResponse {
    return $this->buildNoStoreResponse($extra + [
      'error' => 'forbidden',
      'message' => $message,
    ], 403);
  }

  /**
   * Returns authentication failure response when needed.
   */
  private function buildUnauthenticatedResponse(): ?JsonResponse {
    if ($this->currentUser()->isAuthenticated()) {
      return NULL;
    }

    return $this->buildForbiddenResponse('Authentication is required.');
  }

  /**
   * Checks whether account can use shared sales mode.
   */
  private function canUseSharedSales(AccountInterface $account): bool {
    $isSales = $this->messageManager->resolveViewerRole((int) $account->id()) === 'sales';
    $hasSharedPermission = $account->hasPermission('view shared sales unread counts')
      || $account->hasPermission('administer applications');

    return $isSales || $hasSharedPermission;
  }

  /**
   * Logs throwable and returns server error payload.
   */
  private function buildServerErrorResponse(string $logPrefix, \Throwable $throwable, array $data): JsonResponse {
    $this->getLogger('asu_application')->warning($logPrefix . ': @message', [
      '@message' => $throwable->getMessage(),
    ]);

    return $this->buildNoStoreResponse($data, 500);
  }

}
