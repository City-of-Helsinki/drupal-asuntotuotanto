<?php

namespace Drupal\asu_application\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\UpdateBuildIdCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\asu_application\Event\SalesApplicationEvent;
use Drupal\asu_content\Entity\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form for Application.
 */
class ApplicationForm extends ContentEntityForm {
  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * A cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private ?EventDispatcherInterface $eventDispatcher = NULL;

  protected $application;

  protected function reloadApplication() {
    $application_id = \Drupal::routeMatch()->getParameter('application')
      ?: \Drupal::request()->get('application_id');
    if ($application_id) {
      return \Drupal::entityTypeManager()->getStorage('asu_application')->load($application_id);
    }

    return null;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->requestStack = $container->get('request_stack');
    $instance->cache = $container->get('cache.default');
    $instance->currentPath = $container->get('path.current');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->eventDispatcher = $container->get('event_dispatcher');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (is_null($this->application)) {
      $this->application = $this->reloadApplication();
    }

    $form_state->setRebuild(true);

    $projectReference = $this->entity->project->first();
    $project = $projectReference->entity;

    if (!$project) {
      $project_id = $this->entity->get('project_id')->value;
      $project = $this->entityTypeManager->getStorage('node')->load($project_id);
    }

    $project_id = $project->id();
    $application_type_id = $this->entity->bundle();

    /** @var \Drupal\user\Entity\User $currentUser */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $applicationsUrl = $this->getUserApplicationsUrl();

    $form['#project_id'] = $project_id;
    $form['#project_url'] = Url::fromUri('internal:/node/' . $project_id);

    // Redirect cases.
    if ($currentUser->isAnonymous()) {
      $redirect = '/user/register';
      return (new RedirectResponse($redirect, 301));
    }

    $limit = ['sold', 'reserved', 'reserved_haso'];
    if (!$project_data = $this->getApartments($project, $limit)) {
      $this->logger('asu_application')->critical('User tried to access nonexistent project of id ' . $project_id);
      $this->messenger()->addMessage($this->t('Unfortunately the project you are trying to apply for is unavailable.'));
      return new RedirectResponse($applicationsUrl);
    }

    // Form is filled by customer or salesperson on behalf of the customer.
    if ($currentUser->bundle() == 'customer') {
      $owner_id = $currentUser->id();
      $owner = $currentUser;
    }
    else {
      if (!$owner_id = $this->requestStack->getCurrentRequest()->get('user_id')) {
        $owner_id = $this->entity->getOwnerId();
      }
      $owner = $this->entityTypeManager->getStorage('user')->load($owner_id);
    }

    if ($this->entity->isNew()) {
      $applications = $this->entityTypeManager
        ->getStorage('asu_application')
        ->loadByProperties([
          'uid' => $owner_id,
        ]);

      if ($this->entity->hasField('field_locked') && $this->entity->field_locked->value == 1) {
        $this->messenger()->addMessage($this->t('You have already applied for this project.'));
        return new RedirectResponse($applicationsUrl);
      }

      $parameters = $this->routeMatch->getParameters();

      // If user already has an application for this project.
      if ($project_id = $parameters->get('project_id')) {
        $applications = $this->entityTypeManager
          ->getStorage('asu_application')
          ->loadByProperties([
            'uid' => $owner->id(),
            'project_id' => $project_id,
          ]);

        if (!empty($applications)) {
          $url = reset($applications)->toUrl()->toString();
          (new RedirectResponse($url . '/edit'))->send();
          return $form;
        }
      }

      // Hitas and haso has their own application forms.
      if (!$this->isCorrectApplicationFormForProject($application_type_id, $project_data['ownership_type'])) {
        $this->logger('asu_application')->critical('User tried to access ' . $project_data['ownership_type'] . ' application with project id of ' . $project_id . ' using wrong url.');
        $types = [
          'hitas' => 'haso',
          'haso' => 'hitas',
        ];
        $correctApplicationForm = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() .
          '/application/add/' . $types[strtolower($application_type_id)] . '/' .
          $project_id;
        return new RedirectResponse($correctApplicationForm);
      }

      $startDate = $project_data['application_start_date'];
      $endDate = $project_data['application_end_date'];

      if (!isset($startDate) || !isset($endDate)) {
        $this->logger('asu_application')->critical('User tried to access application form of a project with no start or end date: project id' . $project_id);
        $this->messenger()->addMessage($this->t('The apartment you tried to apply has no start or end date.'));
        return new RedirectResponse($applicationsUrl);
      }

      if ($this->isApplicationPeriod('before', $startDate, $endDate)) {
        $this->messenger()->addMessage($this->t('The application period has not yet started. You cannot send the application until the application period starts.'));
        return new RedirectResponse($applicationsUrl);
      }

      if ($this->isApplicationPeriod('after', $startDate, $endDate)) {
        $this->messenger()->addMessage($this->t('The application period has ended. You can still apply for the apartment by contacting the responsible salesperson.'));
        $freeApplicationUrl = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() .
          '/contact/apply_for_free_apartment?project=' . $project_id;
        return new RedirectResponse($freeApplicationUrl);
      }

      if (is_null($this->eventDispatcher)) {
        $this->eventDispatcher = \Drupal::service('event_dispatcher');
      }

      $this->entity->save();

      $url = $this->entity->toUrl()->toString();
      (new RedirectResponse($url . '/edit'))->send();
      return $form;

    }
    else {
      $form_state->setRebuild(TRUE);

      $projectName = $project_data['project_name'];
      $apartments = $project_data['apartments'];

      // Set the apartments as a value to the form array.
      $form['#apartment_values'] = $apartments;
      $form['#project_name'] = $projectName;

      $form['#project_uuid'] = $project_data['project_uuid'];

      $form = parent::buildForm($form, $form_state);

      $form['#title'] = sprintf('%s %s', $this->t('Application for'), $projectName);

      $form['actions']['submit']['#value'] = $this->t('Send application');
      $form['actions']['submit']['#name'] = 'submit-application';

      // Show draft button only for customers.
      if ($currentUser->bundle() == 'customer') {
        $form['actions']['draft'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save as a draft'),
          '#attributes' => ['class' => ['hds-button--secondary']],
          '#limit_validation_errors' => [],
          '#name' => 'submit-draft',
          '#submit' => ['::submitDraft'],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $formValues = $form_state->cleanValues()->getValues();

    // Main applicant fields.
    foreach ($formValues['main_applicant'][0] as $field => $value) {
      if (empty($value) || $value == '-' || strlen($value) < 2) {
        $fieldTitle = (string) $form["main_applicant"]['widget'][0][$field]['#title'];
        $form_state->setErrorByName($field, $this->t('Field @field cannot be empty', ['@field' => $fieldTitle]));
      }

      if ($field == 'personal_id' && strlen($value) != 4) {
        $fieldTitle = (string) $form["main_applicant"]['widget'][0][$field]['#title'];
        $form_state->setErrorByName($field, $this->t('Check @field', ['@field' => $fieldTitle]));
      }

      if ($field == 'date_of_birth' && empty($this->getPersonalIdDivider($value))) {
        $fieldTitle = (string) $form["main_applicant"]['widget'][0][$field]['#title'];
        $form_state->setErrorByName($field, $this->t('Check @field', ['@field' => $fieldTitle]));
      }

      if ($field == 'postal_code' && (!is_numeric($value) || strlen($value) != 5)) {
        $fieldTitle = (string) $form["main_applicant"]['widget'][0][$field]['#title'];
        $form_state->setErrorByName($field, $this->t('Check @field', ['@field' => $fieldTitle]));
      }
    }

    if (!$this->validatePersonalId($formValues['main_applicant'][0]['date_of_birth'], $formValues['main_applicant'][0]['personal_id'])) {
      $fieldTitle = (string) $form["main_applicant"]['widget'][0]['personal_id']['#title'];
      $form_state->setErrorByName('personal_id', $this->t('Check @field', ['@field' => $fieldTitle]));
    }

    if (count($formValues['apartment']) <= 1 && isset($formValues['apartment'][0])) {
      if ($formValues['apartment'][0]['id'] == '0' || empty($formValues['apartment'][0]['id'])) {
        $form_state->setErrorByName('apartment', $this->t('Field @field cannot be empty', ['@field' => 'apartment']));
      }
    }

    $has_additional_applicant = (!empty($formValues['applicant'][0]['has_additional_applicant'])) ? (bool) $formValues['applicant'][0]['has_additional_applicant'] : FALSE;

    // Additional applicant fields.
    if ($has_additional_applicant) {
      foreach ($formValues['applicant'][0] as $applicant_field => $applicant_value) {
        if ($applicant_field == 'has_additional_applicant') {
          continue;
        }

        if ($applicant_field == 'personal_id' && strlen($applicant_value) != 4) {
          $fieldTitle = (string) $form["applicant"]['widget'][0][$applicant_field]['#title'];
          $form_state->setErrorByName($field, $this->t('Check additional applicant @field', ['@field' => $fieldTitle]));
        }

        if (empty($applicant_value) || $applicant_value == '-' || strlen($applicant_value) < 2) {
          $fieldTitle = (string) $form["applicant"]['widget'][0][$applicant_field]['#title'];
          $form_state->setErrorByName($applicant_field, $this->t('Additional applicant field @field cannot be empty', ['@field' => $fieldTitle]));
        }

        if ($applicant_field == 'postal_code' && (!is_numeric($applicant_value) || strlen($applicant_value) != 5)) {
          $fieldTitle = (string) $form["main_applicant"]['widget'][0][$applicant_field]['#title'];
          $form_state->setErrorByName($applicant_field, $this->t('Check additional applicant @field', ['@field' => $fieldTitle]));
        }
      }

      // Additional applicant personal id check.
      if (!$this->validatePersonalId($formValues['applicant'][0]['date_of_birth'], $formValues['applicant'][0]['personal_id'])) {
        $fieldTitle = (string) $form["applicant"]['widget'][0]['personal_id']['#title'];
        $form_state->setErrorByName('personal_id', $this->t('Check additional applicant @field', ['@field' => $fieldTitle]));
      }
    }

    // Residence number check.
    if (isset($formValues['field_right_of_residence_number'][0]['value']) &&
      !is_numeric($formValues['field_right_of_residence_number'][0]['value']) ||
      (int) $formValues['field_right_of_residence_number'][0]['value'] > 2147483647) {
      $fieldTitle = (string) $form["field_right_of_residence_number"]['widget'][0]['#title'];
      $form_state->setErrorByName($fieldTitle, $this->t('Check @field', ['@field' => $fieldTitle]));
    }

    $triggerName = $form_state->getTriggeringElement()['#name'];
    if ($triggerName == 'submit-application') {
      parent::validateForm($form, $form_state);
    }
    if ($triggerName == 'submit-draft') {
      parent::validateForm($form, $form_state);
      $form_state->clearErrors();
    }
  }

  /**
   * Validate personal id values.
   *
   * @param string $birthDate
   *   Birth date value.
   * @param string $personalId
   *   Personal id value.
   *
   * @return bool
   *   Is personal id validate.
   */
  private function validatePersonalId($birthDate, $personalId): bool {
    $date = new DrupalDateTime($birthDate);
    $control_character = substr($personalId, -1);
    $divider = $this->getPersonalIdDivider($birthDate);
    $alphabet = [
      '0', '1', '2', '3', '4', '5', '6', '7',
      '8', '9', 'A', 'B', 'C', 'D', 'E', 'F',
      'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R',
      'S', 'T', 'U', 'V', 'W', 'X', 'Y',
    ];

    // Check that perosnal id has value and divider is not null.
    if (empty($personalId) || !$divider) {
      return FALSE;
    }

    // Some case personal divider can be in string.
    // Remove divider value on individual number.
    if (strlen($personalId) == 5 && $divider == substr($personalId, 0, 1)) {
      $individual_number = substr($personalId, 1, 3);
    }
    else {
      $individual_number = substr($personalId, 0, 3);
    }

    // Validate birthdate.
    if (!checkdate($date->format('m'), $date->format('d'), $date->format('Y'))) {
      return FALSE;
    }

    // Individual number cannot be 000 or 001.
    if ($individual_number == '000' || $individual_number == '001' || !is_numeric($individual_number)) {
      return FALSE;
    }

    // Checking 9 number integer validation letter.
    $number = $date->format('dmy') . $individual_number;
    $ref = $number % 31;

    if (!str_contains(strtoupper($control_character), $alphabet[$ref])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Submit form without sending it to backend.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitDraft(array &$form, FormStateInterface $form_state) {
    $this->doSave($form, $form_state, FALSE);
    $this->messenger()->addMessage($this->t('The application has been saved as a draft.
     You must submit the application before the application time expires.'));
    $url = Url::fromUri($this->getUserApplicationsUrl(FALSE));
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->doSave($form, $form_state);
    $this->handleApplicationEvent($form, $form_state);
    $email = $form_state->getValue(['main_applicant', 0, 'email']);
    $project_name = $this->entity->get('project')->entity->label() ?? $this->t('Unknown project');

    if (!empty($email)) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'asu_application';
        $key = 'application_submission';
        $params['subject'] = $this->t("Kiitos hakemuksestasi / Thank you for your application");
        $params['message'] = $this->t(
            "Kiitos - olemme vastaanottaneet hakemuksesi kohteeseemme @project_name.\n\n"
            . "Hakemuksesi on voimassa koko rakennusajan.\n\n"
            . "Arvonnan / huoneistojaon jälkeen voit tarkastaa oman sijoituksesi kirjautumalla kotisivuillemme: asuntotuotanto.hel.fi.\n\n"
            . "Tämä on automaattinen viesti – älä vastaa tähän sähköpostiin.\n\n"
            . "------------------------------------------------------------\n\n"
            . "\nThank you - we have received your application for @project_name.\n\n"
            . "Your application will remain valid throughout the construction period.\n\n"
            . "After the lottery / apartment distribution, you can check your position by logging into our website: asuntotuotanto.hel.fi."
            . "This is an automated message – please do not reply to this email.",
            ['@project_name' => $project_name]
        );
        $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
        $send = true;

        $mailManager->mail($module, $key, $email, $langcode, $params, NULL, $send);
    }

    $content_entity_id = $this->entity->getEntityType()->id();
    $form_state->setRedirect("entity.{$content_entity_id}.canonical", [$content_entity_id => $this->entity->id()]);
  }

  /**
   * Handle saving the form values.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param bool $errors
   *   Print error to user.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function doSave(array $form, FormStateInterface $form_state, $errors = TRUE) {
    $values = $form_state->getUserInput();

    $this->updateEntityFieldsWithUserInput($form_state);
    $this->updateApartments($form, $this->entity, $values['apartment']);

    $this->entity->save();

    // Validate additional applicant.
    if ($values['applicant'][0]['has_additional_applicant'] === "1") {
      foreach ($values['applicant'][0] as $value) {
        if (!isset($value) || !$value || $value === '') {
          if ($errors) {
            $this->messenger()->addError($this->t('You must fill all fields for additional applicant before application can be submitted'));
          }
          return;
        }
      }
    }
  }

  /**
   * Handle the application event.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  private function handleApplicationEvent(array $form, FormStateInterface $form_state) {
    $currentUser = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($currentUser->bundle() == 'sales' || $currentUser->hasPermission('administer')) {
      $eventName = SalesApplicationEvent::EVENT_NAME;
      $event = new SalesApplicationEvent(
        $currentUser->id(),
        $this->entity->id(),
        $form['#project_name'],
        $form['#project_uuid'],
      );
    }
    else {
      $eventName = ApplicationEvent::EVENT_NAME;
      $event = new ApplicationEvent(
        $this->entity->id(),
        $form['#project_name'],
        $form['#project_uuid'],
        $this->entity
      );
    }

    if (is_null($this->eventDispatcher)) {
      $this->eventDispatcher = \Drupal::service('event_dispatcher');
    }


    $this->eventDispatcher->dispatch($event, $eventName);
  }

  /**
   * Check that user is filling the correct form.
   *
   * @param string $formType
   *   Form type.
   * @param string $ownershipType
   *   Ownership type.
   *
   * @return bool
   *   Does the apartment ownershiptype match the form's type.
   */
  private function isCorrectApplicationFormForProject($formType, $ownershipType): bool {
    return strtolower($formType) == strtolower($ownershipType);
  }

  /**
   * Get project apartments.
   *
   * @return array
   *   Array of project information & apartments.
   */
  private function getApartments(Project $project, array $limit = []): ?array {
    $cid = 'application_project_apartments_' . $project->id();
    $values = [];
    $type = $project->get('field_ownership_type')
      ?->first()
        ->get('entity')
        ->getTarget()
        ->getValue()
        ->getName();

    if ($apartmentData = $this->cache->get($cid)) {
      $values['apartments'] = $apartmentData->data;
    }
    else {
      $apartments = [];
      foreach ($project->field_apartments as $apartmentReference) {
        $apartment = $apartmentReference->entity;
        // Skip unpublish apartments.
        if ($apartment->get('status')->value == 0) {
          continue;
        }

        $number = $apartment->field_apartment_number->value;

        if (trim(strtolower($number)) == 'a0') {
          continue;
        }

        // Skip wanted state of sale e.g. sold, reserved.
        if ($limit && in_array($apartment->field_apartment_state_of_sale->target_id, $limit)) {
          continue;
        }

        $living_area_size_m2 = number_format($apartment->field_living_area->value, 1, ',', '');
        $structure = $apartment->field_apartment_structure->value;
        $floor = $apartment->field_floor->value;
        $floor_max = $apartment->field_floor_max->value;

        if ($type == 'HASO') {
          $price = $apartment->field_right_of_occupancy_payment->value;
          $second_price = '-';
          $select_text = "$number | $structure | $floor / $floor_max | {$living_area_size_m2} m2 | {$price} € | {$second_price}";
        }
        else {
          $debt_free_sales_price = number_format($apartment->field_debt_free_sales_price->value, 0, ',', ' ');
          $sales_price = number_format($apartment->field_sales_price->value, 0, ',', ' ');
          $select_text = "$number | $structure | $floor / $floor_max | {$living_area_size_m2} m2 | {$sales_price} € | {$debt_free_sales_price} €";
        }

        $apartments[$apartment->id()] = $select_text;
      }
      ksort($apartments, SORT_NUMERIC);
      $this->cache->set($cid, $apartments, Cache::PERMANENT, ['search_api_list:apartment_listing']);

      $values['apartments'] = $apartments;
    }

    return array_merge($values, [
      'project_name' => $project->field_housing_company->value,
      'project_uuid' => $project->uuid(),
      'ownership_type' => $type,
      'application_start_date' => $project->field_application_start_time->value,
      'application_end_date' => $project->field_application_end_time->value,
    ]);

  }

  /**
   * Ajax callback function to presave when triggered by apartment selection.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function saveApplicationCallback(array &$form, FormStateInterface &$form_state) {
    $triggerName = $form_state->getTriggeringElement()['#name'];
    $trigger = (int) preg_replace('/[^0-9]/', '', $triggerName);
    $userInput = $form_state->getUserInput();

    /** @var \Drupal\asu_application\Entity\Application $entity */
    $entity = $form_state->getFormObject()->entity;
    $values = $form_state->getUserInput();

    // Delete.
    if (
      strpos($triggerName, 'apartment') !== FALSE &&
      $form_state->getUserInput()['apartment'][$trigger]['id'] === "0"
    ) {

      unset($userInput['apartment'][$trigger]);
      unset($form['apartment']['widget'][$trigger]);

      // Save apartment values to database.
      $this->updateApartments($form, $entity, $values['apartment']);
      $entity->save();

      $response = new AjaxResponse();
      $response->addCommand(
        new UpdateBuildIdCommand(
          $form['#build_id_old'],
          $form['#build_id']
        ),
        new ReplaceCommand(
          '#edit-apartment-wrapper',
          $form['apartments'],
        ),
      );

      return $response;
    }

    // Save apartment values to database.
    $this->updateApartments($form, $entity, $values['apartment']);
    // Update "has_children" value.
    $entity->set('has_children', $values['has_children']['value'] ?? 0);
    $entity->save();

    $response = new AjaxResponse();
    $response->addCommand(
      new UpdateBuildIdCommand(
        $form['#build_id_old'],
        $form['#build_id']
      ),
      new ReplaceCommand(
        '#edit-apartment-wrapper',
        $form['apartment']
      ),
    );
    return $response;
  }

  /**
   * Update entity.
   *
   * @param array $form
   *   Form.
   * @param Drupal\asu_application\Entity\Application $entity
   *   Application.
   * @param array $apartmentValues
   *   Apartments.
   */
  private function updateApartments(array $form, Application $entity, array $apartmentValues) {
    $apartments = [];
    $sorted = [];
    foreach ($apartmentValues as $apartment) {
      $sorted[$apartment['_weight']] = $apartment;
    }
    ksort($sorted);
    foreach ($sorted as $value) {
      if ($value['id'] == 0 || !$value['id']) {
        continue;
      }
      $apartments[] = [
        'id' => $value['id'],
        'information' => $form['#apartment_values'][$value['id']],
      ];
    }
    $entity->apartment->setValue($apartments);
    return $apartments;
  }

  /**
   * Update the entity with input fields.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormStateInterface.
   */
  private function updateEntityFieldsWithUserInput(FormStateInterface $form_state) {
    foreach ($form_state->getUserInput() as $key => $value) {
      if (in_array($key, $form_state->getCleanValueKeys())) {
        continue;
      }
      if ($key == 'main_applicant' || $key == 'applicant') {
        if (!empty($value[0]['personal_id']) && strlen($value[0]['personal_id']) == 4) {
          $value[0]['personal_id'] = $this->getPersonalIdDivider($value[0]['date_of_birth']) . $value[0]['personal_id'];
        }
      }
      if ($this->entity->hasField($key)) {
        $this->entity->set($key, $value);
      }
    }
  }

  /**
   * Figure out the divider in henkilötunnus.
   *
   * @param string|null $dateString
   *   Date of birth.
   *
   * @return string
   *   Pid divider.
   *
   * @throws \Exception
   */
  private function getPersonalIdDivider(?string $dateString) {
    $dividers = ['18' => '+', '19' => '-', '20' => 'A'];
    $year = (new \DateTime($dateString))->format('Y');
    return (isset($dividers[substr($year, 0, 2)])) ? $dividers[substr($year, 0, 2)] : NULL;
  }

  /**
   * Get url to applications page.
   */
  private function getUserApplicationsUrl($url = TRUE): string {
    if ($url) {
      return $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . '/user/applications';
    }
    return 'internal:/user/applications';
  }

  /**
   * Check application period.
   *
   * @param string $period
   *   Should be either 'before', 'after', or 'now'.
   * @param string $startDate
   *   Project application start date as ISO string.
   * @param string $endDate
   *   Project application end date as ISO string.
   *
   * @return bool
   *   Is application period.
   */
  private function isApplicationPeriod(string $period, string $startDate, string $endDate): bool {
    if (!$startDate || !$endDate) {
      return FALSE;
    }
    $startDate = asu_content_convert_datetime($startDate);
    $startDate = strtotime($startDate);
    $endDate = asu_content_convert_datetime($endDate);
    $endDate = strtotime($endDate);
    $now = time();

    $value = FALSE;

    switch ($period) {
      case "before":
        $value = $now < $startDate;

        break;

      case "now":
        $value = $now > $startDate && $now < $endDate;

        break;

      case "after":
        $value = $now > $endDate;

        break;
    }

    return $value;
  }

}
