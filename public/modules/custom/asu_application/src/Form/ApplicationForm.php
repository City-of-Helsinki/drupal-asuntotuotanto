<?php

namespace Drupal\asu_application\Form;

use Drupal\asu_api\Api\ElasticSearchApi\Request\ProjectApartmentsRequest;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form for Application.
 */
class ApplicationForm extends ContentEntityForm {
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $applicationsUrl = $this->getUserApplicationsUrl();
    $project_id = $this->entity->get('project_id')->value;
    $application_type_id = $this->entity->bundle();

    // Anonymous user must login.
    if (!\Drupal::currentUser()->isAuthenticated()) {
      \Drupal::messenger()->addMessage($this->t('You must be logged in to fill an application'));
      $project_type = strtolower($application_type_id);
      $application_url = "/application/add/$project_type/$project_id";
      $session = \Drupal::request()->getSession();
      $session->set('asu_last_application_url', $application_url);
      return(new RedirectResponse('/user/login', 301));
    }

    // User must have customer role.
    if (!in_array('customer', \Drupal::currentUser()->getRoles())) {
      \Drupal::logger('asu_application')->critical('User without customer role tried to create application: User id: ' . \Drupal::currentUser()->id());
      \Drupal::messenger()->addMessage($this->t('Users without customer role cannot fill applications.'));
      return(new RedirectResponse(\Drupal::request()->getSchemeAndHttpHost(), 301));
    }

    /** @var \Drupal\user\Entity\User $user */
    $user = User::load(\Drupal::currentUser()->id());
    $applications = \Drupal::entityTypeManager()
      ->getStorage('asu_application')
      ->loadByProperties([
        'uid' => \Drupal::currentUser()->id(),
      ]);

    // User must have valid email address to fill more than one applications.
    if ($user->hasField('field_email_is_valid') && $user->field_email_is_valid->value == 0) {
      $application = reset($applications);
      // User must be able to access the one application they have already created.
      if (!empty($applications) && $this->entity->id() === NULL || $application && $application->id() != $this->entity->id()) {
        $this->messenger()->addMessage($this->t('You cannot fill more than one application until you have confirmed your email address.
        To confirm your email you must click the link sent to your email address.'));
        $response = (new RedirectResponse($applicationsUrl, 301))->send();
        return $response;
      }
    }

    if ($this->entity->hasField('field_locked') && $this->entity->field_locked->value == 1) {
      $this->messenger()->addMessage($this->t('This application has already been sent.'));
      return new RedirectResponse($applicationsUrl);
    }

    $parameters = \Drupal::routeMatch()->getParameters();
    $form['#project_id'] = $project_id;
    $bday = $user->date_of_birth->value;

    try {
      $project_data = $this->getApartments($project_id);
    }
    catch (\Exception $e) {
      // Project not found.
      $this->logger('asu_application')->critical('User tried to access nonexistent project of id ' . $project_id);
      $this->messenger()->addMessage($this->t('Project not found'));
      return new RedirectResponse($applicationsUrl);
    }

    // If user already has an application for this project.
    if ($project_id = $parameters->get('project_id')) {
      $applications = \Drupal::entityTypeManager()
        ->getStorage('asu_application')
        ->loadByProperties([
          'uid' => \Drupal::currentUser()->id(),
          'project_id' => $project_id,
        ]);

      if (!empty($applications)) {
        $url = reset($applications)->toUrl()->toString();
        (new RedirectResponse($url . '/edit'))->send();
        return $form;
      }
    }

    if (!$this->isCorrectApplicationFormForProject($application_type_id, $project_data['ownership_type'])) {
      $this->logger('asu_application')->critical('User tried to access ' . $project_data['ownership_type'] . ' application with project id of ' . $project_id . ' using wrong url.');
      $types = [
        'hitas' => 'haso',
        'haso' => 'hitas',
      ];
      $correctApplicationForm = \Drupal::request()->getSchemeAndHttpHost() .
        '/application/add/' . $types[strtolower($application_type_id)] . '/' .
        $project_id;
      return new RedirectResponse($correctApplicationForm);
    }

    $startDate = $project_data['application_start_date'];
    $endDate = $project_data['application_end_date'];

    if (!isset($startDate) || !isset($endDate)) {
      $this->logger()->critical('User tried to access application form of a project with no start or end date: project id' . $project_id);
      $this->messenger()->addMessage($this->t('The apartment you tried to apply has no start or end date.'));
      return new RedirectResponse($applicationsUrl);
    }

    if ($this->isApplicationPeriod('before', $startDate, $endDate)) {
      $this->messenger()->addMessage($this->t('The application period has not yet started'));
      return new RedirectResponse($applicationsUrl);
    }

    if ($this->isApplicationPeriod('after', $startDate, $endDate)) {
      $this->messenger()->addMessage($this->t('The application period has ended. You can still apply for the apartment by contacting us.'));
      $freeApplicationUrl = \Drupal::request()->getSchemeAndHttpHost() .
        '/contact/apply_free_apartment?title=' . $project_data['project_name'];
      return new RedirectResponse($freeApplicationUrl);
    }

    // Pre-create the application if user comes to the form for the first time.
    if ($this->entity->isNew()) {
      if ($this->entity->hasField('field_personal_id')) {
        $personalIdDivider = $this->getPersonalIdDivider($bday);
        $this->entity->set('field_personal_id', $personalIdDivider);
      }

      $this->entity->save();
      $url = $this->entity->toUrl()->toString();
      (new RedirectResponse($url . '/edit'))->send();
      return $form;
    }

    $projectName = $project_data['project_name'];
    $apartments = $project_data['apartments'];

    // Set the apartments as a value to the form array.
    $form['#apartment_values'] = $apartments;
    $form['#project_name'] = $projectName;
    $form['#pid_start'] = $this->dateToPersonalId($bday);
    $form['#project_uuid'] = $project_data['project_uuid'];
    $form['#apartment_uuids'] = $project_data['apartment_uuids'];

    $form = parent::buildForm($form, $form_state);

    $form['#title'] = $this->t('Application for') . ' ' . $projectName;

    $form['actions']['submit']['#value'] = $this->t('Send application');

    $form['actions']['draft'] = [
      '#type' => 'submit',
      '#value' => t('Save as a draft'),
      '#ajax' => [
        'callback' => '::ajaxSaveDraft',
        'event' => 'click',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $values = $form_state->getUserInput();

    $this->updateEntityFieldsWithUserInput($form_state);
    $this->updateApartments($form, $this->entity, $values['apartment']);

    $this->entity->save();

    // Validate additional applicant.
    if ($values['applicant'][0]['has_additional_applicant'] === "1") {
      foreach ($values['applicant'][0] as $key => $value) {
        if (!isset($value) || !$value || $value === '') {
          $this->messenger()->addError($this->t('You must fill all fields for additional applicant before application can be submitted'));
          return;
        }
      }
    }

    $user = User::load(\Drupal::currentUser()->id());
    if ($user->hasField('field_email_is_valid') && $user->field_email_is_valid->value == 1) {
      $event = new ApplicationEvent(
        $this->entity->id(),
        $form['#project_name'],
        $form['#project_uuid'],
        $form['#apartment_uuids']
      );
      $event_dispatcher = \Drupal::service('event_dispatcher');
      $event_dispatcher->dispatch($event, ApplicationEvent::EVENT_NAME);
      $this->entity->set('field_locked', 1);
      $this->entity->save();
      $this->messenger()->addStatus($this->t('Your application has been submitted successfully.
       You can no longer edit the application.'));
      $content_entity_id = $this->entity->getEntityType()->id();
      $form_state->setRedirect("entity.{$content_entity_id}.canonical", [$content_entity_id => $this->entity->id()]);
    }
    else {
      \Drupal::messenger(t('You cannot submit application before you have confirmed your email address.
      To confirm your email address you must click the link sent to your email address.'));
      $response = (new RedirectResponse($this->getUserApplicationsUrl(), 301))->send();
      return $response;
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function ajaxSaveDraft(array $form, FormStateInterface $form_state) {
    $this->updateEntityFieldsWithUserInput($form_state);
    $this->entity->save();

    $this->messenger()->addMessage($this->t('The application has been saved as a draft.
     You must submit the application before the application time expires.'));
    $url = $this->getUserApplicationsUrl();
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($url));
    return $response;
  }

  /**
   * The form should only be active between designated application time.
   *
   * @param string $startTime
   *   Start time as ISO string.
   * @param string $endTime
   *   End time as ISO string.
   *
   * @return bool
   *   Is current moment between the project's application time.
   */
  private function isFormActive(string $startTime, string $endTime): bool {
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    $date = new \DateTime();
    $now = $date->getTimestamp();
    return $now < $end && $now > $start;
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
  private function isCorrectApplicationFormForProject($formType, $ownershipType) {
    return strtolower($formType) == strtolower($ownershipType);
  }

  /**
   * Get project apartments.
   *
   * @return array
   *   Array of project information & apartments.
   */
  private function getApartments($projectId): ?array {
    $projects = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'nid' => $projectId,
      ]);

    if (empty($projects)) {
      return [];
    }

    $project = $projects[$projectId];

    $apartments = [];
    foreach ($project->field_apartments as $apartmentReference) {
      $apartment = $apartmentReference->entity;

      $living_area_size_m2 = number_format($apartment->field_living_area->value, 1, ',', '');
      $debt_free_sales_price = number_format($apartment->field_debt_free_sales_price->value, 0, ',', ' ');
      $sales_price = number_format($apartment->field_sales_price->value, 0, ',', ' ');

      $number = $apartment->field_apartment_number->value;
      $structure = $apartment->field_apartment_structure->value;
      $floor = $apartment->field_floor->value;
      $floor_max = $apartment->field_floor_max->value;

      $select_text = "$number | $structure | $floor / $floor_max |  {$living_area_size_m2} m2 | {$sales_price} € | {$debt_free_sales_price} €";

      $apartments[$apartment->id()] = $select_text;
      $apartmentsUuid[$apartment->id()] = $apartment->uuid();
    }
    ksort($apartments, SORT_NUMERIC);

    return [
      'project_name' => $project->field_housing_company->value,
      'project_uuid' => $project->uuid(),
      'ownership_type' => $project->field_ownership_type->first()->entity->getName(),
      'application_start_date' => $project->field_application_start_time->value,
      'application_end_date' => $project->field_application_end_time->value,
      'apartments' => $apartments,
      'apartment_uuids' => $apartmentsUuid,
    ];
  }

  /**
   * Ajax callback function to presave the form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function saveApplicationCallback(array &$form, FormStateInterface $form_state) {
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
      // Update "has_children" value.
      $entity->set('has_children', $values['has_children']['value'] ?? 0);
      $entity->save();
      return $form['apartment'];
    }

    // Save apartment values to database.
    $this->updateApartments($form, $entity, $values['apartment']);
    // Update "has_children" value.
    $entity->set('has_children', $values['has_children']['value'] ?? 0);
    $entity->save();
    return $form['apartment'];
  }

  /**
   * Update entity.
   *
   * @param $form
   * @param $entity
   * @param $apartmentValues
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
  }

  /**
   * Update the entity with input fields.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  private function updateEntityFieldsWithUserInput(FormStateInterface $form_state) {
    foreach ($form_state->getUserInput() as $key => $value) {
      if (in_array($key, $form_state->getCleanValueKeys())) {
        continue;
      }
      if ($this->entity->hasField($key)) {
        $this->entity->set($key, $value);
      }
    }
  }

  /**
   * Figure out the divider in henkilötunnus.
   *
   * @param string $dateString
   *
   * @return string
   *
   * @throws \Exception
   */
  private function getPersonalIdDivider(?string $dateString) {
    $dividers = ['18' => '+', '19' => '-', '20' => 'A'];
    $year = (new \DateTime($dateString))->format('Y');
    return $dividers[substr($year, 0, 2)];
  }

  /**
   * Turn date into henkilötunnus format "ddmmyy".
   *
   * @param string $dateString
   *
   * @return string
   *
   * @throws \Exception
   */
  private function dateToPersonalId(?string $dateString) {
    $date = new \DateTime($dateString);
    $day = $date->format('d');
    $month = $date->format('m');
    $year = $date->format('y');
    return $day . $month . $year;
  }

  /**
   *
   */
  private function getUserApplicationsUrl(): string {
    return \Drupal::request()->getSchemeAndHttpHost() .
      '/user/' . \Drupal::currentUser()->id() .
      '/applications';
  }

  /**
   * Check application period.
   *
   * @param string $period
   *   Should be either 'before', 'after', or 'during'.
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
    $startTime = strtotime($startDate);
    $endTime = strtotime($endDate);
    $now = time();

    switch ($period) {
      case "before":
        return $now < $startTime;

      break;
      case "during":
        return $now > $startTime && $now < $endTime;

      break;
      case "after":
        return $now > $endTime;

      break;
    }
  }

}
