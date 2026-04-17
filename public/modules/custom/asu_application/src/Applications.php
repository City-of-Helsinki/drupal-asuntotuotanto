<?php

namespace Drupal\asu_application;

use Drupal\user\Entity\User;

/**
 * Handles applications.
 */
class Applications {

  const HIGH_ENUM = 'HIGH';
  const MEDIUM = 10;
  const MEDIUM_ENUM = 'MEDIUM';
  const LOW = 5;
  const LOW_ENUM = 'LOW';

  /**
   * Array of application objects.
   *
   * @var array|\Drupal\Core\Entity\EntityInterface[]
   */
  private array $applications;

  /**
   * Applications constructor.
   *
   * @param string $userId
   *   User id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(?string $userId = NULL) {
    $applicationStorage = \Drupal::entityTypeManager()
      ->getStorage('asu_application');

    try {
      if (!$userId) {
        $this->applications = $applicationStorage->loadMultiple();
      }
      else {
        $applicationIds = $this->resolveApplicationIdsByUser($userId);
        $this->applications = empty($applicationIds)
          ? []
          : $applicationStorage->loadMultiple($applicationIds);
      }
    }
    catch (\Exception $e) {
      $this->applications = [];
    }
  }

  /**
   * Resolve application ids where user is owner or mapped co-applicant.
   */
  private function resolveApplicationIdsByUser(string $userId): array {
    $ids = \Drupal::database()
      ->select('asu_application', 'a')
      ->fields('a', ['id'])
      ->condition('a.uid', (int) $userId)
      ->condition('a.status', 1)
      ->execute()
      ->fetchCol();

    $schema = \Drupal::database()->schema();
    if (!$schema->tableExists('asu_application_co_applicant_map')) {
      return array_map('intval', $ids);
    }

    $user = User::load((int) $userId);
    $samlHash = $user && $user->hasField('field_saml_hash')
      ? $user->get('field_saml_hash')->value
      : NULL;

    if (empty($samlHash)) {
      return array_values(array_unique(array_map('intval', $ids)));
    }

    $coApplicantQuery = \Drupal::database()
      ->select('asu_application_co_applicant_map', 'm');
    $coApplicantQuery->fields('m', ['application_id']);
    $coApplicantQuery->innerJoin('asu_application', 'a', 'a.id = m.application_id');

    $coApplicantIds = $coApplicantQuery
      ->condition('a.status', 1)
      ->condition('m.co_applicant_saml_hash', $samlHash)
      ->execute()
      ->fetchCol();

    $allIds = array_merge($ids, $coApplicantIds);
    return array_values(array_unique(array_map('intval', $allIds)));
  }

  /**
   * Get all applications for all applications.
   *
   * @return static
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function create(): self {
    return new self();
  }

  /**
   * Get applications by user.
   *
   * @param string $userId
   *   User whose applications are resolved.
   *
   * @return static
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function applicationsByUser(string $userId): self {
    return new self($userId);
  }

  /**
   * Get applications for all projects.
   *
   * @return array
   *   Array of apartment ids by project.
   */
  public function getApartmentApplicationsByProject(): array {
    if (empty($this->applications)) {
      return [];
    }
    $applicationsByProject = [];

    /** @var \Drupal\asu_application\Entity\Application $application */
    foreach ($this->applications as $application) {
      $apartmentIds = $application->getApartmentIds();
      $applicationsByProject[$application->getProjectId()] = $apartmentIds;
    }
    return $applicationsByProject;
  }

  /**
   * Returns pairs of project and application ids.
   *
   * @return array
   *   Array of ['project_id' => ..., 'application_id' => ...]
   */
  public function getApplicationsProjectPairs(): array {
    $result = [];
    foreach ($this->applications as $application) {
      $projectId = $application->getProjectId();
      $applicationId = $application->id();
      if ($projectId && $applicationId) {
        $result[] = [
          'project_id' => (int) $projectId,
          'application_id' => (int) $applicationId,
        ];
      }
    }
    return $result;
  }

  /**
   * Get applications for single project.
   *
   * @param int|string $id
   *   Id of the project.
   *
   * @return array
   *   Array of apartment ids by project.
   */
  public function getApartmentApplicationStatusesForProject($id): array {
    if (empty($this->applications)) {
      return [];
    }

    $applicationsForProject = [];

    /** @var \Drupal\asu_application\Entity\Application $application */
    foreach ($this->applications as $application) {
      if ($application->getProjectId() == $id) {
        $applicationsForProject[] = $application;
      }
    }

    $this->applications = $applicationsForProject;

    return $this->getApartmentApplicationStatuses();
  }

  /**
   * Get apartment applications.
   */
  public function getApartmentApplications() {
    if (empty($this->applications)) {
      return [];
    }
    $applications = [];

    /** @var \Drupal\asu_application\Entity\Application $application */
    foreach ($this->applications as $application) {
      $apartmentIds = $application->getApartmentIds();
      $applications = array_merge($applications, $apartmentIds);
    }

    return $applications;
  }

  /**
   * Return application count as status text instead of numeric.
   *
   * @return array
   *   Apartment application status.
   */
  public function getApartmentApplicationStatuses(): array {
    $applications = $this->getApartmentApplications();

    $counts = array_count_values($applications);

    $applicationStatuses = [];

    foreach ($counts as $key => $count) {
      $applicationStatuses[$key] = $this::resolveApplicationCountEnum($count);
    }

    return $applicationStatuses;
  }

  /**
   * Resolve the enum for application count.
   *
   * @param int $count
   *   Number of applications for an apartment.
   *
   * @return string
   *   Application count as enum.
   */
  public static function resolveApplicationCountEnum(int $count): string {
    if ($count === 0) {
      return self::LOW_ENUM;
    }
    if ($count <= self::LOW) {
      return self::LOW_ENUM;
    }
    if (in_array($count, range(self::LOW, self::MEDIUM))) {
      return self::MEDIUM_ENUM;
    }
    if ($count > self::MEDIUM) {
      return self::HIGH_ENUM;
    }
  }

}
