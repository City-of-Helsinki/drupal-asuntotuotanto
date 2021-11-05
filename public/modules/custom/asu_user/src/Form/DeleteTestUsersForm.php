<?php

namespace Drupal\asu_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form which allows deleting all users that start with "test_".
 */
class DeleteTestUsersForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'asu_user_delete_test_users_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $id = NULL
  ) {
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $delete_service = \Drupal::service('asu_user.delete_test_users');
    $delete_service->doDeleteTestUsers();
    \Drupal::messenger()->addMessage($this->t('Test users have been deleted.'));
  }

}
