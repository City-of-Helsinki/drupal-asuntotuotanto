<?php

namespace Drupal\asu_application\Controller;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CustomerOfferMessageRequest;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns offer email content for a customer-owned offer.
 */
class OfferMessageController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly BackendApi $backendApi,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('asu_api.backendapi'),
    );
  }

  /**
   * Fetch offer message subject and body from Django.
   */
  public function message(int $offer_id): JsonResponse {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    if (!$user) {
      return new JsonResponse(['message' => 'Unauthorized.'], 401);
    }

    try {
      $request = new CustomerOfferMessageRequest($user, $offer_id);
      $response = $this->backendApi->send($request);
      $content = $response?->getContent() ?? [];
    }
    catch (\Exception $exception) {
      $this->getLogger('asu_application')->error(
        'Failed to fetch offer message for offer @offer: @message',
        ['@offer' => $offer_id, '@message' => $exception->getMessage()]
      );
      return new JsonResponse(['message' => 'Offer message not found.'], 404);
    }

    if (empty($content['body'])) {
      return new JsonResponse(['message' => 'Offer message not found.'], 404);
    }

    return new JsonResponse([
      'subject' => $content['subject'] ?? '',
      'body' => $content['body'] ?? '',
    ]);
  }

}
