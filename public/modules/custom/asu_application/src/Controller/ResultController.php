<?php

namespace Drupal\asu_application\Controller;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResult;
use Drupal\asu_api\Api\BackendApi\Request\TriggerProjectLotteryRequest;
use Drupal\asu_application\Entity\Application;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
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
    );
  }

  /**
   * Get apartment result array.
   */
  public function getResults(): AjaxResponse {
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    $applicationId = $this->requestStack->getCurrentRequest()->get('application_id');
    if ($user && $applicationId) {
      $application = Application::load($applicationId);

      $project = $this->entityTypeManager()->getStorage('node')->load($application->getProjectId());
      if ($application->getOwnerId() != $user->id()) {
        return new AjaxResponse([], 401);
      }

      $cid = 'asu_application_result_' . $user->id() . '_' . $applicationId;
      if ($cached = $this->cache()->get($cid)) {
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
        $apartment = $this->entity_repository->loadEntityByUuid('node', $result['apartment_uuid']);
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

      $this->cache()->set($cid, json_encode($results), (time() + 60 * 60));
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

}
