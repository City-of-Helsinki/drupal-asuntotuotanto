<?php

namespace Drupal\asu_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\asu_user\DeleteTestUsers;

/**
 * Form which allows deleting all users that start with "test_".
 */
class DeleteTestUsersForm extends FormBase {
  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\asu_user\DeleteTestUsers
   */
  protected $deleteService;

  /**
   * Constructor.
   */
  public function __construct(DeleteTestUsers $delete_service) {
    $this->deleteService = $delete_service;
  }

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
    ?string $id = NULL,
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
    $this->deleteService->doDeleteTestUsers();
    $this->messenger()->addMessage($this->t('Test users have been deleted.'));
  }

}
