<?php

namespace Drupal\asu_application\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\UserRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plugin implementation of the main applicant field widget.
 *
 * @FieldWidget(
 *   id = "asu_main_applicant_widget",
 *   label = @Translation("Asu main applicant - Widget"),
 *   description = @Translation("Asu main applicant - Widget"),
 *   field_types = {
 *     "asu_main_applicant"
 *   },
 * )
 */
class MainApplicantWidget extends WidgetBase {
  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Backend api.
   *
   * @var Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings']);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->backendApi = $container->get('asu_api.backendapi');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    if ($account->hasRole('customer')) {
      $request = new UserRequest($account);
      $request->setSender($account);

      try {
        $userResponse = $this->backendApi->send($request);
      }
      catch (\Exception $e) {
        return new Response('Failed to fetch user data to applicant form.', 400);
      }

      /** @var \Drupal\asu_api\Api\BackendApi\Response\UserResponse $userResponse */
      $userInformation = $userResponse->getUserInformation();
    }
    else {
      $userInformation = [
        'first_name' => NULL,
        'last_name' => NULL,
        'date_of_birth' => NULL,
        'street_address' => NULL,
        'postal_code' => NULL,
        'city' => NULL,
        'phone_number' => NULL,
        'email' => NULL,
      ];
    }

    $element['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#maxlength' => 50,
      '#size' => 100,
      '#default_value' => $items->getValue()[$delta]['first_name'] ?? $userInformation['first_name'],
      '#required' => TRUE,
    ];

    $element['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#maxlength' => 50,
      '#size' => 100,
      '#default_value' => $items->getValue()[$delta]['last_name'] ?? $userInformation['last_name'],
      '#required' => TRUE,
    ];

    $element['date_of_birth'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of birth'),
      '#size' => 30,
      '#default_value' => $items->getValue()[$delta]['date_of_birth'] ?? $userInformation['date_of_birth'],
      '#required' => TRUE,
    ];

    $personal_id_default = (!empty($items->getValue()[$delta]['personal_id'])) ? substr($items->getValue()[$delta]['personal_id'], -4) : NULL;

    $element['personal_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Personal id'),
      '#description' => $this->t('last 4 characters'),
      '#minlength' => 5,
      '#maxlength' => 5,
      '#default_value' => $personal_id_default ?? '',
      '#required' => TRUE,
    ];

    $element['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Street address'),
      '#maxlength' => 99,
      '#default_value' => $items->getValue()[$delta]['address'] ?? $userInformation['street_address'],
      '#required' => TRUE,
    ];

    $element['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal code'),
      '#minlength' => 5,
      '#maxlength' => 5,
      '#size' => 50,
      '#default_value' => $items->getValue()[$delta]['postal_code'] ?? $userInformation['postal_code'],
      '#required' => TRUE,
    ];

    $element['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#maxlength' => 50,
      '#size' => 50,
      '#default_value' => $items->getValue()[$delta]['city'] ?? $userInformation['city'],
      '#required' => TRUE,
    ];

    $element['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number'),
      '#maxlength' => 20,
      '#size' => 20,
      '#default_value' => $items->getValue()[$delta]['phone'] ?? $userInformation['phone_number'],
      '#required' => TRUE,
    ];

    $element['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#maxlength' => 99,
      '#size' => 50,
      '#default_value' => $items->getValue()[$delta]['email'] ?? $userInformation['email'],
      '#required' => TRUE,
    ];

    return $element;
  }

}
