<?php

namespace Drupal\asu_application\Form;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\asu_application\Event\SalesApplicationEvent;
use Drupal\asu_content\Entity\Project;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\UpdateBuildIdCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
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
    $form_state->setRebuild(TRUE);

    $projectReference = $this->entity->project->first();
    $project = $projectReference->entity;

    if (!$project) {
      $project_id = $this->entity->get('project_id')->value;
      $project = Node::load($project_id);
    }

    $project_id = $project->id();
    $application_type_id = $this->entity->bundle();

    /** @var \Drupal\user\Entity\User $currentUser */
    $currentUser = User::load(\Drupal::currentUser()->id());
    $applicationsUrl = $this->getUserApplicationsUrl();

    $form['#project_id'] = $project_id;
    $form['#project_url'] = Url::fromUri('internal:/node/' . $project_id);

    // Redirect cases.
    if ($currentUser->isAnonymous()) {
      $current_path = \Drupal::service('path.current')->getPath();
      $redirect = '/user/login?destination=' . $current_path;
      return (new RedirectResponse($redirect, 301));
    }

    if (!$project_data = $this->getApartments($project)) {
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
      if (!$owner_id = \Drupal::request()->get('user_id')) {
        $owner_id = $this->entity->getOwnerId();
      }
      $owner = User::load($owner_id);
    }

    if ($this->entity->isNew()) {
      $applications = \Drupal::entityTypeManager()
        ->getStorage('asu_application')
        ->loadByProperties([
          'uid' => $owner_id,
        ]);

      if ($this->entity->hasField('field_locked') && $this->entity->field_locked->value == 1) {
        $this->messenger()->addMessage($this->t('You have already applied for this project.'));
        return new RedirectResponse($applicationsUrl);
      }

      $parameters = \Drupal::routeMatch()->getParameters();

      // If user already has an application for this project.
      if ($project_id = $parameters->get('project_id')) {
        $applications = \Drupal::entityTypeManager()
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
        $correctApplicationForm = \Drupal::request()->getSchemeAndHttpHost() .
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
        $freeApplicationUrl = \Drupal::request()->getSchemeAndHttpHost() .
          '/contact/apply_for_free_apartment?project=' . $project_id;
        return new RedirectResponse($freeApplicationUrl);
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
          '#value' => t('Save as a draft'),
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
    foreach ($formValues['main_applicant'][0] as $field => $value) {
      if (empty($value) || $value == '-' || strlen($value) < 2) {
        $fieldTitle = (string) $form["main_applicant"]['widget'][0][$field]['#title'];
        $form_state->setErrorByName($field, t('Field @field cannot be empty', ['@field' => $fieldTitle]));
      }

      if ($field == 'personal_id' && strlen($value) != 4) {
        $fieldTitle = (string) $form["main_applicant"]['widget'][0][$field]['#title'];
        $form_state->setErrorByName($field, t('Check @field', ['@field' => $fieldTitle]));
      }
    }

    if (count($formValues['apartment']) <= 1 && isset($formValues['apartment'][0])) {
      if ($formValues['apartment'][0]['id'] == '0' || empty($formValues['apartment'][0]['id'])) {
        $form_state->setErrorByName('apartment', t('Field @field cannot be empty', ['@field' => 'apartment']));
      }
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
      foreach ($values['applicant'][0] as $key => $value) {
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
    $currentUser = User::load(\Drupal::currentUser()->id());
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
    \Drupal::service('event_dispatcher')
      ->dispatch($event, $eventName);
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
  private function getApartments(Project $project): ?array {
    $cid = 'application_project_apartments_' . $project->id();
    $values = [];
    $type = $project->field_ownership_type->first()->entity->getName();

    if ($apartmentData = \Drupal::cache()->get($cid)) {
      $values['apartments'] = $apartmentData->data;
    }
    else {
      $apartments = [];
      foreach ($project->field_apartments as $apartmentReference) {
        $apartment = $apartmentReference->entity;
        $number = $apartment->field_apartment_number->value;

        if (trim(strtolower($number)) == 'a0') {
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
      \Drupal::cache()->set($cid, $apartments, (time() + 60 * 60));
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
    return $dividers[substr($year, 0, 2)];
  }

  /**
   * Turn date into henkilötunnus format "ddmmyy".
   *
   * @param string|null $dateString
   *   Date of birth.
   *
   * @return string
   *   Date of birth formatted as pid.
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
   * Get url to applications page.
   */
  private function getUserApplicationsUrl($url = TRUE): string {
    if ($url) {
      return \Drupal::request()->getSchemeAndHttpHost() . '/user/applications';
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
    $startTime = strtotime($startDate);
    $endTime = strtotime($endDate);
    $now = time();

    $value = FALSE;

    switch ($period) {
      case "before":
        $value = $now < $startTime;

        break;

      case "now":
        $value = $now > $startTime && $now < $endTime;

        break;

      case "after":
        $value = $now > $endTime;

        break;
    }

    return $value;
  }

}
