<?php

namespace Drupal\asu_application\Form;

use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

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

      }

      $options = [];
      $ownership = [];
      foreach ($projects as $item) {
        if (isset($result[$item->getFields()['project_id']->getValues()[0]])) {
          continue;
        }
        $options[$item->getFields()['project_id']->getValues()[0]] = $item->getFields()['apartment_address']->getValues()[0];
        $ownership[$item->getFields()['project_id']->getValues()[0]] = $item->getFields()['project_ownership_type']->getValues()[0];
      }

      $form['user'] = [
        '#markup' => sprintf('<h3>%s: %s</h3>',$this->t('For user'), $user->getEmail()),
      ];

      $form['user_id'] = [
        '#type' => 'hidden',
        '#value' => $user->id()
      ];

      $form['projects'] = [
        '#type' => 'select',
        '#title' => $this->t('Project'),
        '#options' => $options,
        '#empty_option' => 'Select project',
        '#empty_value' => 0,
        '#required' => TRUE
        #'#default_value' => t('Select project')
      ];

      $form['project_ownership_types'] = [
        '#type' => 'hidden',
        '#value' => json_encode($ownership)
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Select'),
      ];

      return $form;
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create application'),
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();

    if (!isset($values['projects']) || !isset($values['user_id'])) {
      // not possible
    }

    $ownershipTypes = json_decode($values['project_ownership_types'], TRUE);

    $projectId = $values['projects'];
    $userId = $values['user_id'];
    $ownershipType = $ownershipTypes[(int)$projectId];

    $form_state->setRedirect('entity.asu_application.add_form', ['application_type' => strtolower($ownershipType), 'project_id' => $projectId], ['query' => ['user_id' => $userId]]);

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

    return array_filter($projectData, function($item) {
      return (!empty($item->getFields()['project_id']->getValues()) && !empty($item->getFields()['apartment_address']->getValues()));
    });
  }

}
