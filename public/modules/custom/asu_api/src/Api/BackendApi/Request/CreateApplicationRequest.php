<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\CreateApplicationResponse;
use Drupal\asu_api\Api\Request;
use Drupal\asu_application\Entity\Application;
use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A request to create an application.
 */
class CreateApplicationRequest extends Request {
  protected const PATH = '/v1/applications/';
  protected const METHOD = 'POST';
  protected const AUTHENTICATED = TRUE;

  /**
   * Application object.
   *
   * @var Drupal\asu_application\Entity\Application
   */
  protected Application $application;

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
    string $projectUuid
  ) {
    $this->sender = $sender;
    $this->application = $application;
    $this->projectUuid = $projectUuid;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $values = [
      'application_uuid' => $this->application->uuid(),
      'application_type' => $this->application->bundle(),
      'ssn_suffix' => $this->application->main_applicant[0]->personal_id,
      'has_children' => $this->application->getHasChildren(),
      'additional_applicant' => $this->getAdditionalApplicant(),
      'right_of_residence' => NULL,
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

  /**
   * Get apartments.
   *
   * @return array
   *   Array of apartments.
   */
  protected function getApartments(): array {
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
   * Get additional applicant.
   *
   * @return object
   *   Applicant information.
   */
  protected function getAdditionalApplicant(): ?object {
    if (!$this->application->hasAdditionalApplicant()) {
      return NULL;
    }
    $applicant = $this->application->getAdditionalApplicants()[0];
    return (object) [
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
  public static function getResponse(ResponseInterface $response): CreateApplicationResponse {
    return CreateApplicationResponse::createFromHttpResponse($response);
  }

}
