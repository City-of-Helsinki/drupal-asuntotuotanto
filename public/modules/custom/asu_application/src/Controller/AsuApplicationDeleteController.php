<?php

namespace Drupal\asu_application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Controller for Application Deletion.
 */
class AsuApplicationDeleteController extends ControllerBase {

  /**
   * Entity type manager.
   *
   * @var entityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Backend API that is used.
   *
   * @var \Drupal\asu_api\Api\BackendApi\BackendApi
   */
  protected BackendApi $backendApi;

  /**
   * Current user.
   *
   * @var currentUser
   */
  protected $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    BackendApi $backendApi,
    AccountProxyInterface $currentUser,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->backendApi = $backendApi;
    $this->currentUser = $currentUser;
  }

  /**
   * Factory method for new AsuDeleteApplicationController.
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('asu_api.backendapi'),
      $container->get('current_user')
    );
  }

  /**
   * Handles the application deletion request.
   */
  public function delete($application): RedirectResponse {
    $storage = $this->entityTypeManager->getStorage('asu_application');
    $entity = $storage->load($application);

    if (!$entity || $entity->getOwnerId() !== $this->currentUser->id()) {
      $this->messenger()->addError($this->t('Application not found or access denied.'));
      return new RedirectResponse('/user/applications');
    }

    $externalId = $entity->get('field_backend_id')->value;
    if (!$externalId) {
      $this->messenger()->addError($this->t('Missing application ID.'));
      return new RedirectResponse('/user/applications');
    }

    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    try {
      $this->backendApi->deleteApplication($user, $externalId);
      $entity->delete();
      $this->messenger()->addStatus($this->t('Your application has been successfully deleted.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to delete application from backend.'));
    }

    return new RedirectResponse('/user/applications');
  }

}
