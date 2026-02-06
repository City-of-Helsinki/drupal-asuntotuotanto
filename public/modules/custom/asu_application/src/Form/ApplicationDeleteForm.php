<?php

namespace Drupal\asu_application\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Custom delete form to ensure backend applications are cleaned up as well.
 */
class ApplicationDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $backend_id = $entity->get('field_backend_id')->value ?? NULL;

    if ($backend_id) {
      try {
        $user_storage = \Drupal::entityTypeManager()->getStorage('user');
        $user = $user_storage->load(\Drupal::currentUser()->id());
        \Drupal::service('asu_api.backendapi')->deleteApplication($user, $backend_id);
      }
      catch (\Exception $exception) {
        \Drupal::logger('asu_application')->error(
          'Application delete sync failed: @message',
          ['@message' => $exception->getMessage()]
        );
        $this->messenger()->addError($this->t('Failed to delete application from backend.'));
        // Keep the entity intact so the user can retry later.
        $form_state->setRedirectUrl($entity->toUrl('canonical'));
        return;
      }
    }

    parent::submitForm($form, $form_state);
  }

}
