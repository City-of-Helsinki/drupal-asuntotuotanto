<?php

namespace Drupal\asu_application\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResult;
use Drupal\asu_api\Api\BackendApi\Request\TriggerProjectLotteryRequest;
use Drupal\asu_application\Entity\Application;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * List controller.
 */
class ResultController extends ControllerBase {

  public function __construct(
    private readonly BackendApi $backendApi,
    private readonly EntityRepositoryInterface $entity_repository,
    private readonly RequestStack $requestStack,
    private readonly Connection $database,
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('asu_api.backendapi'),
      $container->get('entity.repository'),
      $container->get('request_stack'),
      $container->get('database'),
    );
  }

  /**
   * Get apartment result array.
   */
  public function getResults(): AjaxResponse {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    $applicationId = $this->requestStack->getCurrentRequest()->get('application_id');
    if (!$user || !$applicationId) {
      return new AjaxResponse([]);
    }

    $application = Application::load($applicationId);
    if (!$application) {
      return new AjaxResponse([]);
    }

    $project = $this->entityTypeManager()->getStorage('node')->load($application->getProjectId());
    if (!$project) {
      return new AjaxResponse([]);
    }

    if (!$this->isOwnerOrCoApplicant($application, $user->id())) {
      return new AjaxResponse([], 401);
    }

    $cid = 'asu_application_result_' . $user->id() . '_' . $applicationId;
    if ($cached = $this->cache()->get($cid)) {
      return new AjaxResponse(json_decode($cached->data, TRUE, 200));
    }

    // Backend API authentication data may exist only on the owner account.
    // For mapped co-applicants, fetch lottery results using owner as sender.
    $sender = $user;
    if ((int) $application->getOwnerId() !== (int) $user->id()) {
      $owner = $this->entityTypeManager()->getStorage('user')->load($application->getOwnerId());
      if ($owner) {
        $sender = $owner;
      }
    }

    try {
      $request = new ApplicationLotteryResult($project->uuid());
      $request->setSender($sender);
      /** @var \Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResultResponse $responseContent */
      $responseContent = $this->backendApi
        ->send($request)
        ->getContent();
    }
    catch (\Exception $e) {
      $this->getLogger('asu_api')->critical('Exception when customer tried to access his application results: ' . $e->getMessage());
      return new AjaxResponse([]);
    }

    if (empty($responseContent)) {
      $this->getLogger('asu_api')->warning(
        'Empty reservations response for application @application_id (backend @backend_id), user @user_id, project @project_uuid.',
        [
          '@application_id' => $application->id(),
          '@backend_id' => $application->get('field_backend_id')->value ?? 'N/A',
          '@user_id' => $user->id(),
          '@project_uuid' => $project->uuid(),
        ]
      );
      return new AjaxResponse([]);
    }

    $results = [];
    foreach ($responseContent as $result) {
      $results[] = $this->buildResultItem($result);
    }

    $this->cache()->set($cid, json_encode($results), (time() + 60 * 60));
    return new AjaxResponse($results);
  }

  /**
   * Check whether user is owner or mapped co-applicant for application.
   */
  private function isOwnerOrCoApplicant(Application $application, int $userId): bool {
    if ((int) $application->getOwnerId() === $userId) {
      return TRUE;
    }

    $schema = $this->database->schema();
    if (!$schema->tableExists('asu_application_co_applicant_map')) {
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

  /**
   * Start lottery functionality.
   *
   * @param string $project_uuid
   *   Uuid of the project.
   *
   * @return \Symfony\Component\HttpFoundation\Response|void
   *   Result response.
   */
  public function startLottery(string $project_uuid) {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    if ($user->bundle() != 'sales') {
      return new Response([], 404);
    }
    try {
      $request = new TriggerProjectLotteryRequest($project_uuid);
      $request->setSender($user);
      $this->backendApi->send($request);
    }
    catch (\Exception $e) {
      return new Response('problem with request.', 400);
    }

  }

  /**
   * Build a single result item array from raw backend response data.
   */
  private function buildResultItem(array $result): array {
    $apartment = $this->entity_repository->loadEntityByUuid('node', $result['apartment_uuid']);
    $state = $result['state'] ?? '';

    return [
      'apartment_id' => $apartment ? $apartment->id() : NULL,
      'apartment_uuid' => $result['apartment_uuid'],
      'apartment' => $apartment ? $apartment->field_apartment_number->value : NULL,
      'position' => $result['lottery_position'],
      'current_position' => $result['queue_position'],
      'state' => $state,
      'queue_position' => $result['queue_position'] ?? NULL,
      'queue_position_before_cancelation' => $result['queue_position_before_cancelation'] ?? NULL,
      'cancellation_reason' => $result['cancellation_reason'] ?? NULL,
      'cancellation_reason_label' => $this->resolveCancellationReasonLabel($result['cancellation_reason'] ?? NULL),
      'cancellation_actor' => $result['cancellation_actor'] ?? NULL,
      'cancellation_actor_label' => $this->resolveCancellationActorLabel($result['cancellation_actor'] ?? NULL),
      'cancellation_timestamp' => $result['cancellation_timestamp'] ?? NULL,
      'state_change_events' => $this->parseStateChangeEvents($result['state_change_events'] ?? []),
      'offer' => $this->parseOffer($result['offer'] ?? NULL),
      // phpcs:ignore
      'status' => $this->translateResultValue($state) ?? '-',
    ];
  }

  /**
   * Parse state_change_events from either a JSON string or an array.
   */
  private function parseStateChangeEvents(mixed $raw): array {
    if (is_string($raw)) {
      $decoded = json_decode($raw, TRUE);
      return is_array($decoded) ? $decoded : [];
    }
    return is_array($raw) ? $raw : [];
  }

  /**
   * Parse offer data from raw backend value.
   */
  private function parseOffer(mixed $raw): ?array {
    if (!is_array($raw)) {
      return NULL;
    }
    $offerState = $raw['state'] ?? NULL;
    return [
      'id' => $raw['id'] ?? NULL,
      'created_at' => $raw['created_at'] ?? NULL,
      'valid_until' => $raw['valid_until'] ?? NULL,
      'state' => $offerState,
      'state_label' => $this->translateResultValue($offerState),
      'concluded_at' => $raw['concluded_at'] ?? NULL,
      'comment' => $raw['comment'] ?? NULL,
      'is_expired' => $raw['is_expired'] ?? NULL,
    ];
  }

  /**
   * Translate backend state/reason values to localized labels.
   */
  private function translateResultValue(?string $value): ?string {
    if (!$value) {
      return NULL;
    }

    switch ($value) {
      case 'offered':
        return (string) $this->t('offered');

      case 'pending':
        return (string) $this->t('pending');

      case 'terminated':
        return (string) $this->t('terminated');

      case 'canceled':
      case 'cancelled':
        return (string) $this->t('canceled');

      case 'submitted':
        return (string) $this->t('submitted');

      case 'reserved':
        return (string) $this->t('reserved');

      case 'accepted':
        return (string) $this->t('accepted');

      case 'rejected':
        return (string) $this->t('rejected');

      default:
        return NULL;
    }
  }

  /**
   * Resolve cancellation reason label from backend display/fallback mapping.
   */
  private function resolveCancellationReasonLabel(?string $reason): ?string {
    if (!$reason) {
      return NULL;
    }

    switch ($reason) {
      case 'terminated':
        return (string) $this->t('Agreement terminated');

      case 'canceled':
        return (string) $this->t('Reservation canceled');

      case 'reservation_agreement_canceled':
        return (string) $this->t('Reservation agreement canceled');

      case 'transferred':
        return (string) $this->t('Reservation transferred');

      case 'lower_priority':
        return (string) $this->t('Higher priority apartment acquired');

      case 'other_apartment_offered':
        return (string) $this->t('Another apartment offered in the same project');

      case 'offer_rejected':
        return (string) $this->t('Offer rejected');

      default:
        return NULL;
    }
  }

  /**
   * Resolve cancellation actor label according to seller/system model.
   */
  private function resolveCancellationActorLabel(?string $actor): ?string {
    if (!$actor) {
      return NULL;
    }

    switch ($actor) {
      case 'seller':
        return (string) $this->t('Cancelled by seller');

      case 'system':
        return (string) $this->t('Cancelled by system');

      case 'customer':
        return (string) $this->t('Cancelled by seller');

      default:
        return NULL;
    }
  }

}
