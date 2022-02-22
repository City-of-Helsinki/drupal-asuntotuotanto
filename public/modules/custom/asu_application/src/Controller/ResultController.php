<?php

namespace Drupal\asu_application\Controller;

use Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResult;
use Drupal\asu_api\Api\BackendApi\Request\TriggerProjectLotteryRequest;
use Drupal\asu_application\Entity\Application;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * List controller.
 */
class ResultController extends ControllerBase {

  /**
   * Get apartment result array.
   */
  public function getResults(): AjaxResponse {
    $user = User::load(\Drupal::currentUser()->id());
    $applicationId = \Drupal::request()->get('application_id');
    if ($user && $applicationId) {
      $backendApi = \Drupal::service('asu_api.backendapi');
      $application = Application::load($applicationId);

      $project = Node::load($application->getProjectId());
      if ($application->getOwnerId() != $user->id()) {
        return new AjaxResponse([], 401);
      }

      $cid = 'asu_application_result_' . $user->id() . '_' . $applicationId;

      if ($cached = \Drupal::cache()->get($cid)) {
        return new AjaxResponse(json_decode($cached->data, TRUE, 200));
      }

      try {
        $request = new ApplicationLotteryResult($project->uuid());
        $request->setSender($user);
        /** @var \Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResultResponse $responseContent */
        $responseContent = $backendApi
          ->send($request)
          ->getContent();
      }
      catch (\Exception $e) {
        $this->getLogger('asu_api')->critical('Exception when customer tried to access his application results: ' . $e->getMessage());
        return new AjaxResponse(400, []);
      }

      if (empty($responseContent)) {
        return new AjaxResponse(400, []);
      }

      $results = [];
      foreach ($responseContent as $result) {
        $apartment = \Drupal::service('entity.repository')->loadEntityByUuid('node', $result['apartment_uuid']);
        $results[] = [
          'apartment_id' => $apartment->id(),
          'apartment_uuid' => $result['apartment_uuid'],
          'apartment' => $apartment->field_apartment_number->value,
          'position' => $result['lottery_position'],
          'current_position' => $result['queue_position'],
          'status' => $result['state'],
          // 'status' => t($result['state'])
        ];
      }

      \Drupal::cache()->set($cid, json_encode($results), (time() + 60 * 60));

      return new AjaxResponse($results);
    }
    return new AjaxResponse([], 400);
  }


  public function startLottery(string $project_uuid) {
    $user = User::load(\Drupal::currentUser()->id());
    if ($user->bundle() != 'sales') {
      return new Response('fallssseee');
    }
    try {
      $backendApi = \Drupal::service('asu_api.backendapi');
      $request = new TriggerProjectLotteryRequest($project_uuid);
      $request->setSender($user);
      $content = $backendApi->send($request);

    }
    catch(\Exception $e) {
      return new Response('FAILED');
    }

  }

}
