<?php

namespace Drupal\asu_application\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\UserRequest;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  use ApplicantFormElementsTrait;

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
   * @var \Drupal\asu_api\Api\BackendApi\BackendApi
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
    $account = $this->resolveMainApplicantAccount($items);
    $userInformation = $this->emptyUserInformation();

    if ($account && $account->hasRole('customer')) {
      $request = new UserRequest($account);
      // Authenticate as the profile owner. Django ProfileViewSet only allows
      // reading one's own profile, so salesperson/admin tokens cannot fetch a
      // customer's data. Owner credentials live on the Drupal user entity.
      $request->setSender($account);

      try {
        $userResponse = $this->backendApi->send($request);
        /** @var \Drupal\asu_api\Api\BackendApi\Response\UserResponse $userResponse */
        $userInformation = $userResponse->getUserInformation();
      }
      catch (\Exception $e) {
        // Leave empty defaults so the form still renders when Backend is down
        // or the profile cannot be loaded.
      }
    }

    $storedValues = $items->getValue()[$delta] ?? [];

    return $this->appendApplicantContactFields(
      $element,
      $storedValues,
      $userInformation,
      [
        'required' => TRUE,
        'personal_id_length' => 5,
        'empty_default' => NULL,
      ]
    );
  }

  /**
   * Resolve the customer whose profile should prefill the form.
   *
   * Prefers the application owner so salespersons/admins editing another
   * user's application get that customer's data, not an empty form.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The main applicant field items.
   *
   * @return \Drupal\user\UserInterface|null
   *   Applicant user account, or NULL if unavailable.
   */
  protected function resolveMainApplicantAccount(FieldItemListInterface $items): ?UserInterface {
    $entity = $items->getEntity();
    if ($entity && method_exists($entity, 'getOwner')) {
      $owner = $entity->getOwner();
      if ($owner instanceof UserInterface && $owner->hasRole('customer')) {
        return $owner;
      }
    }

    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    return $account instanceof UserInterface ? $account : NULL;
  }

}
