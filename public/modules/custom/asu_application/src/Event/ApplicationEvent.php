<?php

namespace Drupal\asu_application\Event;

use Drupal\asu_application\Entity\Application;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event for application creation.
 */
class ApplicationEvent extends Event {
  const EVENT_NAME = 'application_created_event';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected $applicationId,
    protected string $projectName,
    protected string $projectUuid,
    protected Application $application
  ) {
  }

  /**
   * Gets the application id.
   */
  public function getApplicationId(): string {
    return $this->applicationId;
  }

  /**
   * Gets the application.
   */
  public function getApplication(): Application {
    return $this->application;
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

}
