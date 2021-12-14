<?php

namespace Drupal\asu_application\Form;

use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form which allows bulk adding images to apartments.
 */
class SalespersonApplicationForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'asu_content_bulk_edit_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $user_id = NULL, string $project_id = NULL) {

    if ($user_id) {
      $user = User::load($user_id);
      try {
        $projects = $this->getProjects();
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t('Failed to fetch projects'));
      }

      $userApplications = \Drupal::entityTypeManager()
        ->getStorage('asu_application')
        ->loadByProperties(['uid' => $user->id()]);

      $options = [];
      $ownership = [];
      foreach ($projects as $key => $project) {
        $options[$key] = $project['title'];
        $ownership[$key] = strtolower($project['ownership_type']);
      }

      $form['user'] = [
        '#markup' => sprintf('<h3>%s: %s</h3>', $this->t('User'), $user->getEmail()),
      ];

      $form['user_applications_title'] = [
        '#markup' => sprintf('<h4>%s</h4>', $this->t('User applications for active projects')),
      ];

      if (!empty($userApplications)) {
        foreach ($userApplications as $key => $application) {
          $status = $application->isLocked() ? $this->t('Already sent:') : $this->t('Draft:');
          $form['user_applications_' . $key] = [
            '#markup' => $status . ' ' . $projects[$application->getProjectId()]['title'] . '<br>',
          ];
        }
      }
      else {
        $form['user_applications_' . $key] = [
          '#markup' => $this->t('User has no applications for active projects.'),
        ];
      }

      $form['user_id'] = [
        '#type' => 'hidden',
        '#value' => $user->id(),
      ];

      $form['projects'] = [
        '#type' => 'select',
        '#title' => $this->t('Project'),
        '#options' => $options,
        '#empty_option' => 'Select project',
        '#empty_value' => 0,
        '#required' => TRUE,
      ];

      $form['project_ownership_types'] = [
        '#type' => 'hidden',
        '#value' => json_encode($ownership),
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create application'),
      ];

      return $form;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();

    if (!isset($values['projects']) || !isset($values['user_id'])) {
      \Drupal::messenger()->addError($this->t('User or project was not selected'));
      return;
    }

    $ownershipTypes = json_decode($values['project_ownership_types'], TRUE);
    $projectId = $values['projects'];
    $userId = $values['user_id'];
    $ownershipType = $ownershipTypes[(int) $projectId];

    \Drupal::request()->query->remove('destination');
    $form_state->setRedirect(
      'entity.asu_application.add_form',
      [
        'application_type' => strtolower($ownershipType),
        'project_id' => (int) $projectId,
      ],
      ['query' => ['user_id' => $userId]],
    );
  }

  /**
   * Get applicable projects.
   *
   * @return array
   *   Array of projects.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  private function getProjects() {
    $indexes = Index::loadMultiple();
    $index = isset($indexes['apartment']) ? $indexes['apartment'] : reset($indexes);
    $query = $index->query();

    $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')
      ->createInstance('direct');
    $parse_mode->setConjunction('AND');
    $query->setParseMode($parse_mode);

    $query->range(0, 10000);

    $query->addCondition('project_state_of_sale', ['upcoming', 'ready'], 'NOT IN');

    $projectData = $query->execute()->getResultItems();

    $projects = [];
    foreach ($projectData as $apartment) {
      if (!empty($apartment->getField('project_id')->getValues()) && !empty($apartment->getField('project_housing_company')->getValues())) {
        if (!isset($projects[$apartment->getField('project_id')->getValues()[0]])) {
          $projects[$apartment->getField('project_id')->getValues()[0]] = [
            'title' => $apartment->getField('project_housing_company')->getValues()[0],
            'ownership_type' => $apartment->getField('project_ownership_type')->getValues()[0],
          ];
        }
      }
    }

    return $projects;
  }

}
