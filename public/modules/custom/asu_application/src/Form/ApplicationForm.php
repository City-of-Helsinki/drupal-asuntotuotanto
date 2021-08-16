<?php

namespace Drupal\asu_application\Form;

use Drupal\asu_api\Api\ElasticSearchApi\Request\ProjectApartmentsRequest;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Event\ApplicationEvent;
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
    if ($this->entity->hasField('field_locked') && $this->entity->field_locked->value === '1') {
      // @todo Redirect to applications page.
    }

    $parameters = \Drupal::routeMatch()->getParameters();

    $project_id = $this->entity->get('project_id')->value;
    $user = User::load($this->entity->getOwner()->id());
    $application_type_id = $this->entity->bundle();
    $form['#project_id'] = $project_id;
    $bday = $user->date_of_birth->value;

    try {
      $project_data = $this->getApartments($project_id);
    }
    catch (\Exception $e) {
      die($e->getMessage());
      // @todo Message & redirect, cannot fetch apartments.
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

    // Pre-create the application if user comes to the form for the first time.
    if ($this->entity->isNew()) {
      $project_id = $parameters->get('project_id');
      /** @var \Drupal\asu_application\Entity\ApplicationType $application */
      $application = $parameters->get('application_type');
      if ($this->entity->hasField('field_personal_id')) {
        $personalIdDivider = $this->getPersonalIdDivider($bday);
        $this->entity->set('field_personal_id', $personalIdDivider);
      }

      $this->entity->save();
      $url = $this->entity->toUrl()->toString();
      (new RedirectResponse($url . '/edit'))->send();
      return $form;
    }

    if (!$this->isCorrectApplicationFormForProject($application_type_id, $project_data['ownership_type'])) {
      // @todo Redirect to correct form.
    }

    $startDate = $project_data['application_start_date'];
    $endDate = $project_data['application_end_date'];

    if (!$this->isFormActive($startDate, $endDate)) {
      // @todo Add redirect to proper place, outside of application time.
      $this->messenger()->addMessage($this->t('You are trying to fill an application which is not active.'));
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

    $form['actions']['draft'] = [
      '#type' => 'submit',
      '#value' => t('Save as a draft'),
      '#submit' => ['::saveAsDraft'],
    ];
    return $form;
  }

  /**
   *
   */
  public function saveAsDraft(array $form, FormStateInterface $form_state) {
    $this->updateEntityFieldsWithUserInput($form_state);
    parent::save($form, $form_state);
    $this->messenger()->addMessage($this->t('The application has been saved. You must submit the application before the application time expires.'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $values = $form_state->getUserInput();

    $this->updateEntityFieldsWithUserInput($form_state);
    $this->updateApartments($form, $this->entity, $values['apartment']);

    parent::save($form, $form_state);
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

    $this->messenger()->addStatus($this->t('Your application has been submitted successfully. You can no longer edit the application.'));
    $content_entity_id = $this->entity->getEntityType()->id();
    $form_state->setRedirect("entity.{$content_entity_id}.canonical", [$content_entity_id => $this->entity->id()]);
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
    return $formType == $ownershipType;
  }

  /**
   * Get project apartments.
   *
   * @return array
   *   Array of project information & apartments.
   */
  private function getApartments($projectId): ?array {
    /** @var \Drupal\asu_api\Api\ElasticSearchApi\ElasticSearchApi $elastic */
    $elastic = \Drupal::service('asu_api.elasticapi');

    $request = new ProjectApartmentsRequest($projectId);
    $apartmentResponse = $elastic->getApartmentService()
      ->getProjectApartments($request);
    $projectName = $apartmentResponse->getProjectName();
    $projectUuid = $apartmentResponse->getProjectUuid();

    $apartments = [];
    foreach ($apartmentResponse->getApartments() as $apartment) {
      $data = $apartment['_source'];

      $living_area_size_m2 = number_format($data['living_area'], 1, ',', '');
      $debt_free_sales_price = number_format($data['debt_free_sales_price'] / 100, 0, ',', ' ');
      $sales_price = number_format($data['sales_price'] / 100, 0, ',', ' ');

      $select_text = "{$data['apartment_number']} | {$data['apartment_structure']} | {$data['floor']} / {$data['floor_max']} | {$living_area_size_m2} m2 | {$sales_price} € | {$debt_free_sales_price} €";

      $apartments[$data['nid']] = $select_text;
      $apartmentsUuid[$data['nid']] = $data['uuid'];
    }
    ksort($apartments, SORT_NUMERIC);

    return [
      'project_name' => $projectName,
      'project_uuid' => $projectUuid,
      'ownership_type' => $apartmentResponse->getOwnershipType(),
      'application_start_date' => $apartmentResponse->getStartTime(),
      'application_end_date' => $apartmentResponse->getEndTime(),
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
  private function getPersonalIdDivider(string $dateString) {
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
  private function dateToPersonalId(string $dateString) {
    $date = new \DateTime($dateString);
    $day = $date->format('d');
    $month = $date->format('m');
    $year = $date->format('y');
    return $day . $month . $year;
  }

}
