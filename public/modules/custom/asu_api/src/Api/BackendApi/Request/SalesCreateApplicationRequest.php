<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\SalesCreateApplicationResponse;
use Drupal\asu_api\Api\Request;
use Drupal\asu_application\Entity\Application;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

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
    $values = [
       // Profile id is the customer profile uuid.
      'profile_id' => $owner->uuid(),
      'application_uuid' => $this->application->uuid(),
      'application_type' => $this->application->bundle(),
      'ssn_suffix' => $this->application->field_personal_id->value,
      'has_children' => $this->application->getHasChildren(),
      'additional_applicant' => $this->getApplicant(),
      // 'right_of_residence' => $this->application
      // ->field_right_of_residence_number->value,
      'project_id' => $this->projectData['uuid'],
      'apartments' => $this->getApartments(),
    ];

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
          'priority' => $key,
          'identifier' => $this->projectData['apartment_uuids'][$value['id']],
        ];
      }
    }
    if (empty($apartments)) {
      throw new \Exception('Application apartments cannot be empty.');
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
      return NULL;
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
