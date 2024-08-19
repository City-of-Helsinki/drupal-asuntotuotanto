<?php

namespace Drupal\asu_application\Controller;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResult;
use Drupal\asu_api\Api\BackendApi\Request\TriggerProjectLotteryRequest;
use Drupal\asu_application\Entity\Application;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * List controller.
 */
class ResultController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Backend api class.
   *
   * @var \Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * A cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a FieldMapperBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\asu_api\Api\BackendApi\BackendApi $backendApi
   *   Api manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The form builder.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountInterface $current_user,
    BackendApi $backendApi,
    EntityRepositoryInterface $entity_repository,
    RequestStack $requestStack,
    CacheBackendInterface $cache,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->backendApi = $backendApi;
    $this->entityRepository  = $entity_repository;
    $this->requestStack = $requestStack;
    $this->cache = $cache;
  }

  /**
   * Get apartment result array.
   */
  public function getResults(): AjaxResponse {
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $applicationId = $this->requestStack->getCurrentRequest()->get('application_id');
    if ($user && $applicationId) {
      $application = Application::load($applicationId);

      $project = $this->entityTypeManager->getStorage('node')->load($application->getProjectId());
      if ($application->getOwnerId() != $user->id()) {
        return new AjaxResponse([], 401);
      }

      $cid = 'asu_application_result_' . $user->id() . '_' . $applicationId;
      if ($cached = $this->cache->get($cid)) {
        return new AjaxResponse(json_decode($cached->data, TRUE, 200));
      }

      try {
        $request = new ApplicationLotteryResult($project->uuid());
        $request->setSender($user);
        /** @var \Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResultResponse $responseContent */
        $responseContent = $this->backendApi
          ->send($request)
          ->getContent();
      }
      catch (\Exception $e) {
        $this->getLogger('asu_api')->critical('Exception when customer tried to access his application results: ' . $e->getMessage());
        return new AjaxResponse([], 400);
      }

      if (empty($responseContent)) {
        return new AjaxResponse([], 400);
      }

      $results = [];
      foreach ($responseContent as $result) {
        $apartment = $this->entityRepository->loadEntityByUuid('node', $result['apartment_uuid']);
        $results[] = [
          'apartment_id' => $apartment->id(),
          'apartment_uuid' => $result['apartment_uuid'],
          'apartment' => $apartment->field_apartment_number->value,
          'position' => $result['lottery_position'],
          'current_position' => $result['queue_position'],
          // phpcs:ignore
          'status' => t($result['state']),
        ];
      }

      $this->cache->set($cid, json_encode($results), (time() + 60 * 60));
      return new AjaxResponse($results);
    }
    return new AjaxResponse([], 400);
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
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
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

}
