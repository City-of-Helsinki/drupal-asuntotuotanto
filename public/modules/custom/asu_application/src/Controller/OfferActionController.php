<?php

namespace Drupal\asu_application\Controller;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResult;
use Drupal\asu_api\Api\BackendApi\Request\OfferActionRequest;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Service\OfferNotificationService;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles customer accept/reject actions for apartment offers.
 */
class OfferActionController extends ControllerBase {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly BackendApi $backendApi,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly Connection $database,
    private readonly CacheBackendInterface $cache,
    private readonly OfferNotificationService $offerNotification,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('asu_api.backendapi'),
      $container->get('entity.repository'),
      $container->get('database'),
      $container->get('cache.default'),
      $container->get('asu_application.offer_notification'),
    );
  }

  /**
   * Accept or reject an offer on behalf of the logged-in customer.
   */
  public function action(
    Request $request,
    int $offer_id,
    string $action,
  ): JsonResponse {
    if (!in_array($action, ['accept', 'reject'], TRUE)) {
      return new JsonResponse(['message' => 'Invalid action.'], 400);
    }

    $applicationId = (int) $request->request->get('application_id');
    if ($applicationId <= 0) {
      return new JsonResponse(['message' => 'Missing application_id.'], 400);
    }

    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    if (!$user) {
      return new JsonResponse(['message' => 'Unauthorized.'], 401);
    }

    $application = Application::load($applicationId);
    if (!$application) {
      return new JsonResponse(['message' => 'Application not found.'], 404);
    }

    if (!$this->isOwnerOrCoApplicant($application, (int) $user->id())) {
      return new JsonResponse(['message' => 'Forbidden.'], 403);
    }

    $project = $this->entityTypeManager()->getStorage('node')->load($application->getProjectId());
    if (!$project) {
      return new JsonResponse(['message' => 'Project not found.'], 404);
    }

    if (!$this->userOwnsOffer($user, $application, $project->uuid(), $offer_id)) {
      return new JsonResponse(['message' => 'Offer not found.'], 404);
    }

    $sender = $user;
    if ((int) $application->getOwnerId() !== (int) $user->id()) {
      $owner = $this->entityTypeManager()->getStorage('user')->load($application->getOwnerId());
      if ($owner) {
        $sender = $owner;
      }
    }

    $state = $action === 'accept' ? 'accepted' : 'rejected';
    try {
      $response = $this->backendApi->send(
        new OfferActionRequest($sender, $offer_id, $state)
      );
    }
    catch (\Exception $exception) {
      $this->getLogger('asu_api')->error(
        'Offer action failed for offer @offer: @message',
        ['@offer' => $offer_id, '@message' => $exception->getMessage()]
      );
      return new JsonResponse(['message' => 'Offer action failed.'], 400);
    }

    $offerData = $response?->getContent() ?? [];
    $offerData['apartment_uuid'] = $this->findApartmentUuidForOffer(
      $sender,
      $project->uuid(),
      $offer_id
    );

    if ($action === 'accept') {
      $this->offerNotification->sendAcceptedNotification(
        $application,
        $project,
        $offerData,
        $user,
      );
    }
    else {
      $this->offerNotification->sendRejectedNotification(
        $application,
        $project,
        $offerData,
        $user,
      );
    }

    $this->cache->delete('asu_application_result_' . $user->id() . '_' . $applicationId);
    if ((int) $application->getOwnerId() !== (int) $user->id()) {
      $this->cache->delete(
        'asu_application_result_' . $application->getOwnerId() . '_' . $applicationId
      );
    }

    return new JsonResponse([
      'success' => TRUE,
      'offer' => $offerData,
    ]);
  }

  /**
   * Verify the offer belongs to the customer's reservations.
   */
  private function userOwnsOffer(
    $user,
    Application $application,
    string $projectUuid,
    int $offerId,
  ): bool {
    $sender = $user;
    if ((int) $application->getOwnerId() !== (int) $user->id()) {
      $owner = $this->entityTypeManager()->getStorage('user')->load($application->getOwnerId());
      if ($owner) {
        $sender = $owner;
      }
    }

    try {
      $request = new ApplicationLotteryResult($projectUuid);
      $request->setSender($sender);
      $results = $this->backendApi->send($request)?->getContent() ?? [];
    }
    catch (\Exception) {
      return FALSE;
    }

    foreach ($results as $result) {
      $offer = $result['offer'] ?? NULL;
      if (is_array($offer) && (int) ($offer['id'] ?? 0) === $offerId) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Find apartment UUID for an offer from reservation results.
   */
  private function findApartmentUuidForOffer(
    $sender,
    string $projectUuid,
    int $offerId,
  ): string {
    try {
      $request = new ApplicationLotteryResult($projectUuid);
      $request->setSender($sender);
      $results = $this->backendApi->send($request)?->getContent() ?? [];
    }
    catch (\Exception) {
      return '';
    }

    foreach ($results as $result) {
      $offer = $result['offer'] ?? NULL;
      if (is_array($offer) && (int) ($offer['id'] ?? 0) === $offerId) {
        return (string) ($result['apartment_uuid'] ?? '');
      }
    }

    return '';
  }

  /**
   * Check whether user is owner or mapped co-applicant for application.
   */
  private function isOwnerOrCoApplicant(Application $application, int $userId): bool {
    if ((int) $application->getOwnerId() === $userId) {
      return TRUE;
    }

    if (!$this->database->schema()->tableExists('asu_application_co_applicant_map')) {
      return FALSE;
    }

    $account = $this->entityTypeManager()->getStorage('user')->load($userId);
    if (!$account || !$account->hasField('field_saml_hash')) {
      return FALSE;
    }

    $samlHash = $account->get('field_saml_hash')->value;
    if (empty($samlHash)) {
      return FALSE;
    }

    $exists = $this->database
      ->select('asu_application_co_applicant_map', 'm')
      ->fields('m', ['application_id'])
      ->condition('application_id', (int) $application->id())
      ->condition('co_applicant_saml_hash', $samlHash)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return (bool) $exists;
  }

}
