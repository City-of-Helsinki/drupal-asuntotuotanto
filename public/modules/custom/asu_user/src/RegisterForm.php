<?php

namespace Drupal\asu_user;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\asu_api\Exception\RequestException;
use Drupal\asu_api\Exception\ResponseParameterException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\RegisterForm as BaseForm;

/**
 * Customized registration form.
 */
class RegisterForm extends BaseForm {

  /**
   * Backend api class.
   *
   * @var \Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  private Store $store;

  /**
   * Construct.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, BackendApi $backendApi, Store $store) {
    parent::__construct($entity_repository, $language_manager, $entity_type_bundle_info, $time);
    $this->backendApi = $backendApi;
    $this->store = $store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('asu_api.backendapi'),
      $container->get('asu_user.tempstore')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $config = \Drupal::config('asu_user.external_user_fields');
    $fields = $config->get('external_data_map');

    foreach ($fields as $field => $info) {
      $form['basic_information'][$field] = [
        '#type' => $info['type'],
        '#title' => $this->t($info['title']),
        '#maxlength' => 255,
        '#required' => TRUE,
        '#attributes' => [
          'autocorrect' => 'off',
          'autocapitalize' => 'off',
          'spellcheck' => 'false',
        ],
        '#default_value' => '',
      ];
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!filter_var($form_state->getUserInput()['mail'], FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('mail', $this->t('Invalid email format'));
    }
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $account = $this->entity;

    // Default login flow.
    $pass = $account->getPassword();
    $admin = $form_state->getValue('administer_users');
    $notify = !$form_state->isValueEmpty('notify');
    $account->save();

    // Create user to backend.
    if ($account->hasRole('customer')) {
      $this->store->setMultipleByConfiguration($form_state->getUserInput());
      $this->sendToBackend($account, $form_state);

      $form_state->set('user', $account);
      $form_state->setValue('uid', $account->id());

      $this->logger('user')->notice('New user: %name %email.', ['%name' => $form_state->getValue('name'), '%email' => '<' . $form_state->getValue('mail') . '>', 'type' => $account->toLink($this->t('Edit'), 'edit-form')->toString()]);

      // Add plain text password into user account to generate mail tokens.
      $account->password = $pass;

      user_login_finalize($account);
    }
  }

  /**
   * Send the user information to Django backend.
   */
  private function sendToBackend(UserInterface $account, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $account */
    try {
      $request = new CreateUserRequest($account, $form_state->getUserInput());
      $response = $this->backendApi
        ->getUserService()
        ->createUser($request);

      $account->field_backend_profile = $response->getProfileId();
      $account->field_backend_password = $response->getPassword();
      $account->save();
    }
    catch (ResponseParameterException $e) {
      // @todo Proper logging and error handling.
      // Request failed.
      $this->messenger()->addError('Backend returned unsatisfactory parameters.' . $e->getMessage());
    }
    catch (RequestException $e) {
      $this->messenger()->addError('Backend returned non-200 response:' . $e->getMessage());
    }
    catch (\Exception $e) {
      // Something unexpected happened.
      $this->messenger()->addError('Unexpected exception while sending user to backend: ' . $e->getMessage());
    }
  }

}
