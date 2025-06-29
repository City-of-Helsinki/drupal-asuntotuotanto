<?php

namespace Drupal\asu_user;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\user\UserInterface;
use Drupal\user_bundle\TypedRegisterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Customized registration form.
 */
class RegisterForm extends TypedRegisterForm {
  /**
   * Backend api class.
   *
   * @var \Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  /**
   * Construct.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   Entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   EntityTypeBundleInfoInterface.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   Time interface.
   * @param \Drupal\asu_api\Api\BackendApi $backendApi
   *   Backend api.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    ?TimeInterface $time = NULL,
    BackendApi $backendApi,
  ) {
    parent::__construct($entity_repository, $language_manager, $entity_type_bundle_info, $time);
    $this->backendApi = $backendApi;
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
    );
  }

  /**
   * {@inheritDoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form_object = $form_state->getFormObject();

    if ($form_object->getEntity()->bundle() != 'customer') {
      return $form;
    }

    // @codingStandardsIgnoreStart
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
    // @codingStandardsIgnoreEnd

    // Show "create application for new customer" button for sales.
    if (
      \Drupal::currentUser()->isAuthenticated() &&
      in_array('salesperson', \Drupal::currentUser()->getRoles(TRUE)) ||
      \Drupal::currentUser()->hasPermission('administer') &&
      $form_object->getFormId() == 'user_customer_register_form'
    ) {
      $form['create_application'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create application for new user'),
        '#button_type' => 'primary',
        '#submit' => ['::createApplication'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $fields_to_clean = ['postal_code', 'phone_number'];

    foreach ($fields_to_clean as $field) {
      $value = $form_state->getValue($field);
      if (!empty($value)) {
        $form_state->setValue($field, str_replace('-', '', $value));
      }
    }
    if (!filter_var($form_state->getUserInput()['mail'], FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('mail', $this->t('Invalid email format'));
    }
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $currentUser = \Drupal::currentUser();
    $form_id = $form_state->getFormObject()->getFormId();
    if (
      $currentUser->isAuthenticated() &&
      in_array('salesperson', $currentUser->getRoles(TRUE)) ||
      $currentUser->hasPermission('Administer permissions')
    ) {
      if ($form_id == 'user_customer_register_form') {
        $this->salespersonCreatesCustomer($form_state);
      }
      if ($form_id == 'user_sales_register_form') {
        $this->saveSalesperson($form_state);
      }
    }
    else {
      if ($form_id == 'user_customer_register_form') {
        $this->saveCustomer($form_state);
      }
    }
  }

  /**
   * Customer creates new account.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function saveCustomer(FormStateInterface $form_state) {
    /** @var \Drupal\user_bundle\Entity\TypedUser $account */
    $account = $this->entity;
    $pass = $account->getPassword();

    if (!$account->hasRole('customer')) {
      $account->addRole('customer');
    }

    try {
      $saved = $account->save();

      if ($saved === SAVED_NEW) {
        asu_user_asu_send_user_email('asu_new_customer_registered', $account);
      }

      $request = new CreateUserRequest($account, $form_state->getUserInput());
      $request->setSender($account);
      $this->sendToBackend($account, $request);

      $form_state->set('user', $account);
      $form_state->setValue('uid', $account->id());
      $this->logger('user')->notice('New user: %name %email.',
        [
          '%name' => $form_state->getValue('name'),
          '%email' => '<' . $form_state->getValue('mail') . '>',
          'type' => $account->toLink($this->t('Edit'), 'edit-form')
            ->toString(),
        ]);
      // Add plain text password into user account to generate mail tokens.
      $account->password = $pass;
      user_login_finalize($account);
    }
    catch (\Exception $e) {
      \Drupal::logger('asu_user')->emergency('Customer failed to create an account: ' . $e->getMessage());
    }
  }

  /**
   * Sales person creates new customer account.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function salespersonCreatesCustomer(FormStateInterface $form_state) {
    $pass = $form_state->getValues()['pass'];

    $user = $form_state->getFormObject()->entity;

    $formValues = $form_state->getValues();
    $user->setPassword($pass);
    $user->enforceIsNew();
    $user->setEmail($formValues['mail']);

    $hash = substr(base64_encode(microtime()), 0, 7);
    $user->setUsername(
      sprintf(
        '%s_%s_%s',
        $formValues['first_name'],
        $formValues['last_name'],
        $hash
      )
    );

    $user->set('init', 'email');
    $user->set('langcode', $formValues['preferred_langcode']);
    $user->set('preferred_langcode', $formValues['preferred_langcode']);
    $user->set('preferred_admin_langcode', $formValues['preferred_admin_langcode']);
    $user->set('timezone', 'Europe/Helsinki');

    $user->addRole('customer');
    $user->activate();

    $user->save();
    if ($formValues['notify'] == TRUE) {
      asu_user_asu_send_user_email('asu_sales_registered_new_customer', $user);
    }

    if ($form_state->getTriggeringElement()['#parents'][0] == 'create_application') {
      $form_state->setRedirect('asu_application.admin_create_application', ['user_id' => $user->id()]);
    }

    $request = new CreateUserRequest($user, $form_state->getUserInput());
    $request->setSender($user);
    $this->sendToBackend($user, $request);

    $form_state->set('user', $user);
    $form_state->setValue('uid', $user->id());
    $this->logger('user')->notice('New user: %name %email.',
      [
        '%name' => $form_state->getValue('name'),
        '%email' => '<' . $form_state->getValue('mail') . '>',
        'type' => $user->toLink($this->t('Edit'), 'edit-form')
          ->toString(),
      ]);
    $user->password = $pass;
    \Drupal::messenger()->addMessage(
      $this->t('New customer account was created: @email', ['@email' => $form_state->getValue('mail')])
    );
  }

  /**
   * Salesperson creates new salesperson.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function saveSalesperson(FormStateInterface $form_state) {
    /** @var \Drupal\user_bundle\Entity\TypedUser $account */
    $account = $this->entity;
    $pass = $account->getPassword();

    if (!$account->hasRole('salesperson')) {
      $account->addRole('salesperson');
    }

    $account->save();

    $formValues = $form_state->getValues();
    if ($formValues['notify'] == TRUE) {
      asu_user_asu_send_user_email('asu_new_salesperson_registered', $account);
    }

    $phone = '-';
    if ($account->hasField('field_phone_number')) {
      $phone = $account->field_phone_number->value ?? '-';
    }

    $accountData = [
      'first_name' => '-',
      'last_name' => '-',
      'address' => '-',
      'phone_number' => $phone,
      'postal_code' => '-',
      'city' => '-',
      'date_of_birth' => (new \Datetime())->format('Y-m-d'),
    ];

    $request = new CreateUserRequest($account, $accountData, 'salesperson');
    $request->setSender($account);
    $this->sendToBackend($account, $request);

    $form_state->set('user', $account);
    $form_state->setValue('uid', $account->id());
    $this->logger('user')->notice('New user: %email.',
      [
        '%email' => '<' . $form_state->getValue('mail') . '>',
        'type' => $account->toLink($this->t('Edit'), 'edit-form')
          ->toString(),
      ]);

    $this->messenger()->addMessage(
      $this->t('New sales user created: @email', ['@email' => $form_state->getValue('mail')])
    );

    $account->password = $pass;
  }

  /**
   * Send the user information to Django backend.
   */
  private function sendToBackend(UserInterface $account, CreateUserRequest $request) {
    try {
      /** @var \Drupal\asu_api\Api\BackendApi\Response\CreateUserResponse $response */
      $response = $this->backendApi->send($request);
      $account->field_backend_profile = $response->getProfileId();
      $account->field_backend_password = $response->getPassword();
      $account->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('asu_backend_api')->emergency(
        'Exception while creating user to backend: ' . $e->getMessage()
      );
    }
  }

  /**
   * Callback, application creation form.
   */
  public function createApplication(array $form, FormStateInterface $form_state) {
    $this->salespersonCreatesCustomer($form_state);
  }

}
