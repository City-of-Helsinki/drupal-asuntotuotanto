<?php

namespace Drupal\asu_application\Plugin\Field\FieldWidget;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\UserRequest;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the applicant field widget.
 *
 * @FieldWidget(
 *   id = "asu_applicant_widget",
 *   label = @Translation("Asu applicant - Widget"),
 *   description = @Translation("Asu applicant - Widget"),
 *   field_types = {
 *     "asu_applicant"
 *   },
 * )
 */
class ApplicantWidget extends WidgetBase implements ContainerFactoryPluginInterface {
  use ApplicantFormElementsTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Backend api.
   *
   * @var \Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings']
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->backendApi = $container->get('asu_api.backendapi');
    $instance->database = $container->get('database');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'asu_application/additional-applicant';

    $userInformation = $this->emptyUserInformation();
    $storedValues = $items->getValue()[$delta] ?? [];
    $hasStoredApplicant = !$items->isEmpty();

    // When applicant data was cleaned after submit, restore from the mapped
    // co-applicant Backend profile (same idea as main applicant prefilling).
    if (!$hasStoredApplicant) {
      $account = $this->resolveAdditionalApplicantAccount($items);
      if ($account && $account->hasRole('customer')) {
        $request = new UserRequest($account);
        $request->setSender($account);

        try {
          $userResponse = $this->backendApi->send($request);
          /** @var \Drupal\asu_api\Api\BackendApi\Response\UserResponse $userResponse */
          $userInformation = $userResponse->getUserInformation();
        }
        catch (\Exception $e) {
          // Keep empty defaults when profile fetch fails.
        }
      }
    }

    $hasAdditionalApplicant = $hasStoredApplicant
      || !empty($userInformation['first_name'])
      || !empty($userInformation['last_name']);

    $element['has_additional_applicant'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add additional applicant'),
      '#default_value' => $hasAdditionalApplicant,
    ];

    $element['applicant_prefix'] = [
      '#type' => 'markup',
      '#markup' => '<div id="applicant-wrapper" class="application-form__applicant-form">',
    ];

    $element['application_information_prefix'] = [
      '#type' => 'markup',
      '#markup' => '<div class="application-form__application-information">',
    ];

    $element['application_information_tooltip'] = [
      '#type' => 'markup',
      '#markup' => '<p class="application-form__application-information-tooltip">' . $this->t('
      This applicant cannot complete another application for the same item.') . '</p>',
    ];

    $element['application_information_suffix'] = [
      '#type' => 'markup',
      '#markup' => '</div>',
    ];

    $element = $this->appendApplicantContactFields(
      $element,
      $storedValues,
      $userInformation,
      [
        'required' => FALSE,
        'personal_id_length' => 4,
        'empty_default' => '',
      ]
    );

    $element['applicant_suffix'] = [
      '#type' => 'markup',
      '#markup' => '</div>',
    ];

    return $element;
  }

  /**
   * Resolve a mapped co-applicant Drupal user for profile prefilling.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The additional applicant field items.
   *
   * @return \Drupal\user\UserInterface|null
   *   Co-applicant user account, or NULL if unavailable.
   */
  protected function resolveAdditionalApplicantAccount(FieldItemListInterface $items): ?UserInterface {
    $entity = $items->getEntity();
    if (!$entity || !$entity->id()) {
      return NULL;
    }

    if (!$this->database->schema()->tableExists('asu_application_co_applicant_map')) {
      return NULL;
    }

    $samlHash = $this->database->select('asu_application_co_applicant_map', 'm')
      ->fields('m', ['co_applicant_saml_hash'])
      ->condition('application_id', (int) $entity->id())
      ->execute()
      ->fetchField();

    if (!$samlHash) {
      return NULL;
    }

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'field_saml_hash' => $samlHash,
    ]);
    $account = reset($users);

    return $account instanceof UserInterface ? $account : NULL;
  }

}
