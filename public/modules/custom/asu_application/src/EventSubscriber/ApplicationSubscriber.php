<?php

namespace Drupal\asu_application\EventSubscriber;

use Drupal\asu_api\Api\BackendApi\Request\CreateApplicationRequest;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Application subscriber.
 */
class ApplicationSubscriber implements EventSubscriberInterface {
  use MessengerTrait;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Backend api.
   *
   * @var Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  /**
   * Queueworker.
   *
   * @var Drupal\Core\Queue\QueueInterface
   */
  private QueueInterface $queue;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\asu_api\Api\BackendApi\BackendApi $backendApi
   *   Api manager.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory.
   */
  public function __construct(LoggerInterface $logger, BackendApi $backendApi, QueueFactory $queueFactory) {
    $this->logger = $logger;
    $this->backendApi = $backendApi;
    $this->queue = $queueFactory->get('application_api_queue');
  }

  /**
   * Get subscribed events.
   *
   * @return array
   *   The event names to listen to.
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ApplicationEvent::EVENT_NAME][] = ['sendApplicationToBackend', 5];
    return $events;
  }

  /**
   * Sends application to backend.
   *
   * @param \Drupal\asu_application\Event\ApplicationEvent $applicationEvent
   *   Application event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function sendApplicationToBackend(ApplicationEvent $applicationEvent) {
    $entity_type = 'asu_application';
    $entity_id = $applicationEvent->getApplicationId();

    /** @var \Drupal\asu_application\Entity\Application $application */
    $application = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $user = $application->getOwner();

    try {
      $request = new CreateApplicationRequest(
        $user,
        $application,
        [
          'uuid' => $applicationEvent->getProjectUuid(),
          'apartment_uuids' => $applicationEvent->getApartmentUuids(),
        ]
      );
      $this->backendApi->send($request);
      // @todo Notice in event.
      $this->logger->notice('User sent an application to backend successfully');
    }
    catch (\Exception $e) {
      $this->logger->critical(sprintf(
        'Exception while sending application %s: %s',
        $application->id(),
        $e->getMessage()
      ));
      $this->queue->createItem($application->id());
    }

  }

}
