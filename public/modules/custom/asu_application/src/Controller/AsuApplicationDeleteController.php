<?php

namespace Drupal\asu_application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\Core\Session\AccountProxyInterface;

class AsuApplicationDeleteController extends ControllerBase {

  protected $entityTypeManager;
  protected BackendApi $backendApi;
  protected $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    BackendApi $backendApi,
    AccountProxyInterface $currentUser
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->backendApi = $backendApi;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('asu_api.backendapi'),
      $container->get('current_user')
    );
  }

  public function delete($application, $token): RedirectResponse {
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
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to delete application from backend.'));
      return new RedirectResponse('/user/applications');
    }

    $entity->delete();
    $this->messenger()->addStatus($this->t('Your application has been successfully deleted.'));
    return new RedirectResponse('/user/applications');
  }

}
