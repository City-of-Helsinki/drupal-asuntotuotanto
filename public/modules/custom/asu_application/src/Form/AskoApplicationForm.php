<?php

namespace Drupal\asu_application\Form;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\asu_content\Entity\Project;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\UpdateBuildIdCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form for Application.
 */
class AskoApplicationForm extends ContentEntityForm {
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    // $form_state->disableCache();
    $projectReference = $this->entity->project->first();
    $project = $projectReference->entity;

    if (!$project) {
      $project_id = $this->entity->get('project_id')->value;
      $project = Node::load($project_id);
    }

    $project_id = $project->id();
    $application_type_id = $this->entity->bundle();

    // @todo muualle jos jotain.
    $applicationsUrl = '<front>';

    $form['#project_id'] = $project_id;
    $form['#project_url'] = Url::fromUri('internal:/node/' . $project_id);

    if (!$project_data = $this->getApartments($project)) {
      $this->logger('asu_application')->critical('User tried to access nonexistent project of id ' . $project_id);
      $this->messenger()->addMessage($this->t('Unfortunately the project you are trying to apply for is unavailable.'));
      return new RedirectResponse($applicationsUrl);
    }

    $projectName = $project_data['project_name'];
    $apartments = $project_data['apartments'];

    // Set the apartments as a value to the form array.
    $form['#apartment_values'] = $apartments;
    $form['#project_name'] = $projectName;

    $form['#project_uuid'] = $project_data['project_uuid'];

    $form = parent::buildForm($form, $form_state);

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

    // @todo tarkista apply_for_free_apartment alterit.
    if ($this->isApplicationPeriod('after', $startDate, $endDate)) {
      $this->messenger()->addMessage($this->t('The application period has ended. You can still apply for the apartment by contacting the responsible salesperson.'));
      $freeApplicationUrl = \Drupal::request()->getSchemeAndHttpHost() .
        '/contact/apply_for_free_apartment?project=' . $project_id;
      return new RedirectResponse($freeApplicationUrl);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Apartment check.
    if ($form_state->getValue('apartment')) {
      // Get apartment data.
      $apartments = $form_state->getValue('apartment');
      // Clear add more.
      if (isset($apartments['add_more'])) {
        unset($apartments['add_more']);
      }
      // Check that apartment has more than 1 value.
      // Check that if first apartment data if 0.
      if (count($apartments) <= 1 && $apartments[0]['id'] == 0) {
        $form_state->setErrorByName('apartment', $this->t('Select at least one apartment.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $eventName = ApplicationEvent::EVENT_NAME;
    $event = new ApplicationEvent(
      $this->entity->id(),
      $form['#project_name'],
      $form['#project_uuid'],
      $this->entity
    );

    \Drupal::service('event_dispatcher')
      ->dispatch($event, $eventName);
    // @todo korvaa muualle.
    $form_state->setRedirect('<front>');
  }

  /**
   * Handle the application event.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  private function handleApplicationEvent(array $form, FormStateInterface $form_state): void {
    $eventName = ApplicationEvent::EVENT_NAME;
    $event = new ApplicationEvent(
      $this->entity->id(),
      $form['#project_name'],
      $form['#project_uuid'],
    );

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

        $living_area_size_m2 = number_format($apartment->field_living_area->value, 1, ',', '');
        $number = $apartment->field_apartment_number->value;
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
      if ($this->entity->hasField($key)) {
        $this->entity->set($key, $value);
      }
    }
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
