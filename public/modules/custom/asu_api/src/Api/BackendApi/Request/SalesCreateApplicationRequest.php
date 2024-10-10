<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\SalesCreateApplicationResponse;
use Drupal\asu_api\Api\Request;
use Drupal\asu_application\Entity\Application;
use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A request to create an application.
 */
class SalesCreateApplicationRequest extends CreateApplicationRequest {
  protected const PATH = '/v1/sales/applications/';

  /**
   * Project uuid.
   *
   * @var string
   */
  private string $projectUuid;

  /**
   * Constructor.
   */
  public function __construct(
    UserInterface $sender,
    Application $application,
    string $projectUuid,
  ) {
    $this->sender = $sender;
    $this->application = $application;
    $this->projectUuid = $projectUuid;
  }

  /**
   * Data to array.
   *
   * @return array
   *   Array which is sent to API.
   */
  public function toArray(): array {
    /** @var \Drupal\user\UserInterface $owner */
    $owner = $this->application->getOwner();

    $values = [
      // Profile id is the customer profile uuid.
      'profile' => $owner->uuid(),
      'application_uuid' => $this->application->uuid(),
      'application_type' => $this->application->bundle(),
      'applicant' => $this->getMainApplicant(),
      'has_children' => $this->application->getHasChildren(),
      'additional_applicant' => $this->getAdditionalApplicant(),
      'right_of_residence' => NULL,
      'is_new_permit_number' => NULL,
      'project_id' => $this->projectUuid,
      'apartments' => $this->getApartments(),
      'is_right_of_occupancy_housing_changer' => FALSE,
      'has_hitas_ownership' => FALSE,
      'right_of_residence_is_old_batch' => $this->application->hasNewPermitNumber(),
    ];

    if ($this->application->hasField('field_right_of_residence_number')) {
      $values['right_of_residence'] = $this->application->field_right_of_residence_number->value;
    }

    if ($this->application->hasField('field_aso_changer')) {
      $values['is_right_of_occupancy_housing_changer'] = (bool) $this->application->field_aso_changer->value;
    }

    if ($this->application->hasField('field_hitas_owner')) {
      $values['has_hitas_ownership'] = (bool) $this->application->field_hitas_owner->value;
    }

    return $values;
  }

}
