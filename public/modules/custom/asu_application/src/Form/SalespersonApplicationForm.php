<?php

namespace Drupal\asu_application\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allow salesperson to create an application on behalf of customer.
 */
class SalespersonApplicationForm extends FormBase {
  use MessengerTrait;

  /**
   * Constructs a FieldMapperBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\search_api\ParseMode\ParseModePluginManager $parseModeManager
   *   The parse mode manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date
   *   The date service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ParseModePluginManager $parseModeManager,
    protected DateFormatterInterface $date,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.search_api.parse_mode'),
      $container->get('date.formatter'),
    );
  }

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
      $projects = [];
      $user = $this->entityTypeManager->getStorage('user')->load($user_id);
      try {
        $projects = $this->getProjects();
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Failed to fetch projects'));
      }

      $userApplications = $this->entityTypeManager
        ->getStorage('asu_application')
        ->loadByProperties(['uid' => $user->id()]);

      $options = [];
      $ownership = [];
      $key = NULL;
      foreach ($projects as $key => $project) {
        $options[$key] = $project['title'];
        $ownership[$key] = (isset($project['ownership_type'])) ? strtolower($project['ownership_type']) : NULL;
      }

      $form['user'] = [
        '#markup' => sprintf('<h3>%s: %s</h3>', $this->t('User'), $user->getEmail()),
      ];

      $form['user_applications_title'] = [
        '#markup' => sprintf('<h4>%s</h4>', $this->t('User applications for active projects')),
      ];

      if (!empty($userApplications)) {
        foreach ($userApplications as $key => $application) {
          /** @var \Drupal\asu_application\Entity\Application $application  */
          $status = $application->isLocked() ? $this->t('Already sent') : $this->t('Draft');
          $latest_change = $this->date->format($application->getLatestTimestamp(), 'long');
          $form['user_applications_' . $key] = [
            '#markup' => $status . ' ( ' . $latest_change . ' ): ' . $projects[$application->getProjectId()]['title'] . '<br>',
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
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();

    if (!isset($values['projects']) || !isset($values['user_id'])) {
      $this->messenger()->addError($this->t('User or project was not selected'));
      return;
    }

    $ownershipTypes = json_decode($values['project_ownership_types'], TRUE);
    $projectId = $values['projects'];
    $userId = $values['user_id'];
    $ownershipType = $ownershipTypes[(int) $projectId];

    $form_state->setIgnoreDestination();

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
    $index = $indexes['apartment'] ?? reset($indexes);
    $query = $index->query();

    $parse_mode = $this->parseModeManager->createInstance('direct');
    $parse_mode->setConjunction('AND');
    $query->setParseMode($parse_mode);

    $query->range(0, 10000);

    $query->addCondition('project_state_of_sale', ['upcoming', 'ready'], 'NOT IN');

    $projectData = $query->execute()->getResultItems();

    $projects = [];
    foreach ($projectData as $apartment) {
      if (!empty($apartment->getField('project_id')->getValues()[0]) && !empty($apartment->getField('project_housing_company')->getValues()[0])) {
        if (!isset($projects[$apartment->getField('project_id')->getValues()[0]])) {
          $projects[$apartment->getField('project_id')->getValues()[0]] = [
            'title' => $apartment->getField('project_housing_company')->getValues()[0] ?? NULL,
            'ownership_type' => $apartment->getField('project_ownership_type')->getValues()[0] ?? NULL,
          ];
        }
      }
    }

    return $projects;
  }

}
