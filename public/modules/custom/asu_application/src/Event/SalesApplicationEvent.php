<?php

namespace Drupal\asu_application\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event for application created by Salesperson.
 */
class SalesApplicationEvent extends Event {
  const EVENT_NAME = 'application_created_by_sales_event';

  /**
   * Id of the user sending the application.
   *
   * @var string
   */
  private string $senderId;

  /**
   * Id of the application to be sent.
   *
   * @var string
   */
  private string $applicationId;

  /**
   * Name of the project.
   *
   * @var string
   */
  private string $projectName;

  /**
   * Project uuid.
   *
   * @var string
   */
  private string $projectUuid;

  /**
   * Selected apartments.
   *
   * @var array
   */
  private array $apartmentUuids;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    string $senderId,
    string $applicationId,
    string $projectName,
    string $projectUuid,
    array $apartmentUuids) {
    $this->senderId = $senderId;
    $this->applicationId = $applicationId;
    $this->projectName = $projectName;
    $this->projectUuid = $projectUuid;
    $this->apartmentUuids = $apartmentUuids;
  }

  /**
   * Gets the application id.
   */
  public function getApplicationId(): string {
    return $this->applicationId;
  }

  /**
   * Get the name of the project.
   *
   * @return string
   *   Name of the project.
   */
  public function getProjectName(): string {
    return $this->projectName;
  }

  /**
   * Project uuid.
   */
  public function getProjectUuid(): string {
    return $this->projectUuid;
  }

  /**
   * Apartment uuids.
   */
  public function getApartmentUuids(): array {
    return $this->apartmentUuids;
  }

}
