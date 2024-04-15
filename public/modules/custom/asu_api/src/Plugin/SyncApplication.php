<?php

declare(strict_types=1);

namespace Drupal\asu_api\Plugin\QueueWorker;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateApplicationRequest;
use Drupal\asu_application\Entity\Application;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes failed application requests.
 *
 * @QueueWorker(
 *   id = "application_api_queue",
 *   title = @Translation("Sync order to backend api."),
 *   cron = {"time" = 60}
 * )
 */
class SyncApplication extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Backend api.
   *
   * @var Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BackendApi $backendApi) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->backendApi = $backendApi;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('asu_api.apimanager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $application = Application::load($data);
    try {
      /** @var \Drupal\node\Entity\Node $project */
      $project = Node::load($application->getProjectId());

      /** @var \Drupal\node\Entity\Node[] $apartments */
      $apartments = Node::loadMultiple($application->getApartmentIds());
      $apartmentData = [];
      foreach ($apartments as $apartment) {
        $apartmentData[$apartment->id()] = $apartment->uuid();
      }

      $request = new CreateApplicationRequest($application->getOwner(), $application, $project->uuid());
      $this->backendApi->send($request);
    }
    catch (\Exception $e) {
      // @todo Logger should maybe log about this particular application.
      throw $e;
    }
  }

}
