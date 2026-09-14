<?php

declare(strict_types=1);

namespace Drupal\asu_application\Controller;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Service\ApplicationPaymentSyncService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns application payments for on-demand UI refresh.
 */
final class ApplicationPaymentController extends ControllerBase {

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly ApplicationPaymentSyncService $paymentSync,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('request_stack'),
      $container->get('asu_application.payment_sync'),
    );
  }

  /**
   * Fetch payments for one application visible to current user.
   */
  public function getPayments(): JsonResponse {
    $applicationId = (int) ($this->requestStack->getCurrentRequest()->get('application_id') ?? 0);
    if ($applicationId <= 0) {
      return $this->response([]);
    }

    $application = Application::load($applicationId);
    if (!$application) {
      return $this->response([]);
    }

    if (!$application->access('view', $this->currentUser(), TRUE)->isAllowed()) {
      return $this->response([], 403);
    }

    return $this->response($this->paymentSync->getPaymentsForApplication($applicationId));
  }

  /**
   * Build uncached JSON response.
   */
  private function response(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    $response->headers->set('Pragma', 'no-cache');

    return $response;
  }

}
