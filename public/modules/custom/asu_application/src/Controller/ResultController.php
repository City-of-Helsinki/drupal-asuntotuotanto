<?php

namespace Drupal\asu_application\Controller;

use Drupal\asu_application\Entity\Application;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;

/**
 * List controller.
 */
class ResultController extends ControllerBase {

  /**
   * Get apartment result array.
   */
  public function getResults() {
    $user = User::load(\Drupal::currentUser()->id());
    $applicationId = \Drupal::request()->get('application_id');
    if ($user && $applicationId) {
      $backendApi = \Drupal::service('asu_api.backendapi');
      $application = Application::load($applicationId);

      if (!$application->getOwnerId() != $user->id()) {
        // Access denied.
      }

      try {
        // $request = new ApplicationLotteryResult($user->uuid, $application->getProjectId());
        // $request->setSender($user);
        /** @var \Drupal\asu_api\Api\BackendApi\Request\ApplicationLotteryResultResponse $response */
        // $responseContent = $backendApi->send($request)->getContent();
        $responseContent = [
          [
            [
              'apartment_id' => '343',
              'apartment' => 'A1',
              'lottery_position' => '3',
              'position' => '3',
              'status' => 'reserved',
            ],
            [
              'apartment_id' => '352',
              'apartment' => 'A10',
              'lottery_position' => '5',
              'position' => '4',
              'status' => 'reserved',
            ],
            [
              'apartment_id' => '356',
              'apartment' => 'A14',
              'lottery_position' => '5',
              'position' => '3',
              'status' => 'reserved',
            ],
            [
              'apartment_id' => '362',
              'apartment' => 'A20',
              'lottery_position' => '1',
              'position' => '1',
              'status' => 'reserved',
            ],
            [
              'apartment_id' => '365',
              'apartment' => 'A23',
              'lottery_position' => '2',
              'position' => '2',
              'status' => 'reserved',
            ],
          ],
        ];
        return new AjaxResponse($responseContent);
      }
      catch (\Exception $e) {

      }
    }
  }

}
