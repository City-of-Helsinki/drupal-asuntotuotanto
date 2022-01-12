<?php

namespace Drupal\asu_application\Controller;

use Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResult;
use Drupal\asu_application\Entity\Application;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

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

      try {
        $request = new ApplicationLotteryResult($user->uuid(), $project->uuid());
        $request->setSender($user);

        /** @var \Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResultResponse $response */
        $responseContent = $backendApi
          ->send($request)
          ->getContent();
      }
      catch (Exception $e) {
        $this->getLogger('asu_api')->critical('Exception when customer tried to access his application results: ' . $e->getMessage());
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
          'status' => t($result['status']),
        ];
      }
      return new AjaxResponse($results);
    }
    return new AjaxResponse([], 400);
  }

}
