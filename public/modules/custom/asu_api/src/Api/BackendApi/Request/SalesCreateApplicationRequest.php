<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\SalesCreateApplicationResponse;
use Drupal\asu_application\Entity\Application;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;
use Drupal\asu_api\Api\Request;

/**
 * A request to create an application.
 */
class SalesCreateApplicationRequest extends Request {
  protected const PATH = '/v1/sales/applications/';
  protected const METHOD = 'POST';
  protected const AUTHENTICATED = TRUE;

  /**
   * Application object.
   *
   * @var Drupal\asu_application\Entity\Application
   */
  private Application $application;

  /**
   * Project data.
   *
   * @var array
   */
  private array $projectData;

  /**
   * Constructor.
   */
  public function __construct(
    UserInterface $sender,
    Application $application,
    array $projectData
  ) {
    $this->sender = $sender;
    $this->application = $application;
    $this->projectData = $projectData;
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

    $applicant = $this->getApplicant();
    if (!$applicant) {
      $applicant = NULL;
    }

    $values = [
      // Profile id is the customer profile uuid.
      'profile' => $owner->uuid(),
      'application_uuid' => $this->application->uuid(),
      'application_type' => $this->application->bundle(),
      'ssn_suffix' => $this->application->field_personal_id->value,
      'has_children' => $this->application->getHasChildren(),
      'additional_applicant' => $applicant,
      'project_id' => $this->projectData['uuid'],
      'right_of_residence' => NULL,
      'apartments' => $this->getApartments(),
      'has_hitas_ownership' => FALSE,
      'is_right_of_occupancy_housing_changer' => FALSE,
    ];

    if ($this->application->hasField('field_right_of_residence_number')) {
      $values['right_of_residence'] = $this->application->field_right_of_residence_number->value;
    }

    if ($this->application->hasField('aso_changer')) {
      $values['is_right_of_occupancy_housing_changer'] = $this->application->field_aso_changer->value ?? FALSE;
    }

    if ($this->application->hasField('hitas_owner')) {
      $values['has_hitas_ownership'] = $this->application->field_hitas_owner->value ?? FALSE;
    }

    return $values;
  }

  /**
   * Get apartments.
   *
   * @return array
   *   Array of apartments.
   */
  private function getApartments() {
    $apartments = [];
    foreach ($this->application->getApartments()->getValue() as $key => $value) {
      if (isset($value['id'])) {
        $apartments[$key] = [
          'priority' => $key + 1,
          'identifier' => $this->projectData['apartment_uuids'][$value['id']],
        ];
      }
    }
    if (empty($apartments)) {
      throw new \InvalidArgumentException('Application apartments cannot be empty.');
    }
    return $apartments;
  }

  /**
   * Get primary applicant.
   *
   * @return array
   *   Applicant information.
   */
  private function getApplicant() {
    if (!$this->application->hasAdditionalApplicant()) {
      return [];
    }
    $applicant = $this->application->getApplicants()[0];
    return [
      'first_name' => $applicant['first_name'],
      'last_name' => $applicant['last_name'],
      'email' => $applicant['email'],
      'phone_number' => $applicant['phone'],
      'street_address' => $applicant['address'],
      'city' => $applicant['city'],
      'postal_code' => $applicant['postal_code'],
      'date_of_birth' => $applicant['date_of_birth'],
      'ssn_suffix' => $applicant['personal_id'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): SalesCreateApplicationResponse {
    return SalesCreateApplicationResponse::createFromHttpResponse($response);
  }

}
