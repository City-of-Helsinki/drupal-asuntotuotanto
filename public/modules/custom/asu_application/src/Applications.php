<?php

namespace Drupal\asu_application;

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
  public function __construct(?string $type, $id) {
    $applicationStorage = \Drupal::entityTypeManager()
      ->getStorage('asu_application');

    $condition = [
      'field_locked' => 1,
    ];

    switch ($type) {
      case 'user':
        $condition['uid'] = $id;
        break;

      case 'project':
        $condition['project_id'] = $id;
        break;

      case 'apartment':
        $condition['apartment'] = $id;
        break;

      default:
        $condition = [];
    }

    $this->applications = $applicationStorage->loadByProperties($condition);
  }

  /**
   * Get applications based on type.
   *
   * @param string $by
   *   By "customer" or by "project" or by "apartment".
   * @param string $id
   *   Id of the customer, project or apartment.
   *
   * @return \Drupal\asu_application\Entity\Applications
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function get(string $by = NULL, string $id = NULL): Applications {
    return new self($by, $id);
  }

  /**
   * Get all application entities.
   *
   * @return array
   */
  public function getApplications(): array {
    return $this->applications;
  }

  /**
   * Get project ids for applications.
   *
   * @return array
   *   Projects with applications.
   */
  public function getProjectIds(): array {
    $projectIds = [];
    foreach ($this->applications as $application) {
      $projectIds[] = $application->getProjectId();
    }
    return $projectIds;
  }

  /**
   * Get the application count.
   *
   * @param bool $enum
   *   Return as enum or numeric.
   *
   * @return array
   *   Application count by apartment.
   */
  public function getApplicationCount($enum = TRUE): array {
    if (!$this->applications) {
      return [];
    }
    /** @var \Drupal\asu_application\Entity\Application $x */

    $counts = [];
    /** @var \Drupal\asu_application\Entity\Application $application */
    foreach ($this->applications as $application) {
      foreach ($application->getApartmentIds() as $id) {
        $counts[$id] = isset($counts[$id]) ? $counts[$id] + 1 : 1;
      }
    }

    if ($enum) {
      $enums = [];
      foreach ($counts as $key => $count) {
        $enums[$key] = self::resolveApplicationCountEnum($count);
      }
      return $enums;
    }

    return $counts;
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

    return '';
  }

}
