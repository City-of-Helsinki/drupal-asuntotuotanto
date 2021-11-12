<?php

namespace Drupal\asu_user;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
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
   * User store.
   *
   * @var \Drupal\asu_user\Store
   */
  private Store $store;

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
   * @param \Drupal\asu_user\Customer $customer
   *   User store.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL,
    BackendApi $backendApi,
    Customer $customer) {
    parent::__construct($entity_repository, $language_manager, $entity_type_bundle_info, $time);
    $this->backendApi = $backendApi;
    $this->customer = $customer;
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
      $container->get('asu_user.customer')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $config = \Drupal::config('asu_user.external_user_fields');
    $fields = $config->get('external_data_map');
    $form_object = $form_state->getFormObject();
    if ($form_object->getEntity()->bundle() != 'customer') {
      return $form;
    }
    foreach ($fields as $field => $info) {
      $form['basic_information'][$field] = [
        '#type' => $info['type'],
        '#title' => $this->t(
          "@{$info['title']}",
          ["@{$info['title']}" => $info['title']]
        ),
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
    $pass = $account->getPassword();
    $account->save();
    // Create user to backend.
    if ($account->bundle() == 'customer') {
      $this->customer->updateUserExternalFields($form_state->getUserInput());
      $this->sendToBackend($account, $form_state);
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
  }

  /**
   * Send the user information to Django backend.
   */
  private function sendToBackend(UserInterface $account, FormStateInterface $form_state) {
    try {
      $request = new CreateUserRequest($account, $form_state->getUserInput());
      /** @var \Drupal\asu_api\Api\BackendApi\Response\CreateUserResponse $response */
      $response = $this->backendApi->send($request);

      $account->field_backend_profile = $response->getProfileId();
      $account->field_backend_password = $response->getPassword();
      $account->save();
    }
    catch (\Exception $e) {
      // Something unexpected happened.
      $this->messenger()->addError('Unexpected exception while sending user to backend: ' . $e->getMessage());
    }
  }

}
