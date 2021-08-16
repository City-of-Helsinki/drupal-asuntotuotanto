<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\Request;
use Drupal\asu_application\Entity\Application;
use Drupal\user\UserInterface;
use phpDocumentor\Reflection\Types\Integer;

/**
 * Create application request.
 */
class CreateApplicationRequest extends Request {
  /**
   * Api path.
   *
   * @var string
   */
  protected const PATH = '/v1/applications/';

  /**
   * Method.
   *
   * @var string
   */
  protected const METHOD = 'POST';

  /**
   * User object.
   *
   * @var \Drupal\user\Entity\UserInterface|UserInterface
   */
  private UserInterface $user;

  private Application $application;

  private array $projectData;

  /**
   * Constructor.
   *
   * @param \Drupal\user\Entity\UserInterface $user
   *   Owner of the application.
   * @param \Drupal\asu_application\Entity\Application $application
   *   Application.
   */
  public function __construct(
    UserInterface $user,
    Application $application,
    array $projectData
  ) {
    $this->user = $user;
    $this->application = $application;
    $this->projectData = $projectData;
  }

  /**
   *
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
   * Calculates person's age from PID.
   *
   * @param string $pid
   *   Personal identification number.
   *
   * @return int
   *   Age.
   */
  private function calculateAgeFromPid(string $pid): Integer {
    $century = substr($pid, 7, 1) === "-" ? 19 : 20;
    $year = substr($pid, 5, 2);

    $day = substr($pid, 1, 2);
    $month = substr($pid, 3, 2);

    $date = "{$day}-{$month}-{$century}{$year}";

    return $this->dateDifferenceYears($date);
  }

  /**
   * Get difference between two dates in years.
   *
   * @param string $date
   *   Date to compare to 'now'.
   *
   * @return int
   *   Difference between now and the given date in years.
   */
  private function dateDifferenceYears(string $date) {
    $date = new \DateTime($date);
    $now = new \DateTime();
    $interval = $now->diff($date);
    return $interval->y;
  }

  /**
   *
   */
  private function getApplicant() {
    if (!$this->application->hasAdditionalApplicant()) {
      return NULL;
    }
    $applicant = $this->application->getApplicants()[0];
    // @todo Use external field map configuration.
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
   * Data to array.
   *
   * @return array
   *   Array which is sent to API.
   */
  public function toArray(): array {
    $values = [
      'application_uuid' => $this->application->uuid(),
      'application_type' => $this->application->bundle(),
      'ssn_suffix' => $this->application->field_personal_id->value,
      'has_children' => $this->application->getHasChildren(),
      'additional_applicant' => $this->getApplicant(),
      'right_of_residence_number' => $this->application->field_right_of_residence_number->value,
      'project_id' => $this->projectData['uuid'],
      'apartments' => $this->getApartments(),
    ];

    return $values;
  }

}
