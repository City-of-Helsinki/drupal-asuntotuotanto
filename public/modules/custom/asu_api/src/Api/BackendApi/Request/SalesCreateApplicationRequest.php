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
      'ssn_suffix' => $this->application->field_personal_id->value,
      'has_children' => $this->application->getHasChildren(),
      'additional_applicant' => $this->getApplicant(),
      'right_of_residence' => NULL,
      'project_id' => $this->projectUuid,
      'apartments' => $this->getApartments(),
      'is_right_of_occupancy_housing_changer' => FALSE,
      'has_hitas_ownership' => FALSE,
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
        $apartmentUuid = Node::load($value['id'])->uuid();
        $apartments[$key] = [
          'priority' => $key + 1,
          'identifier' => $apartmentUuid,
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
