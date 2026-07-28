<?php

namespace Drupal\asu_application\Form;

use Drupal\asu_application\Service\LateApplicationChecker;
use Drupal\asu_application\Service\SalespersonApplicationProjectProvider;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allow salesperson to create an application on behalf of customer.
 */
class SalespersonApplicationForm extends FormBase {
  use MessengerTrait;

  /**
   * Constructs a SalespersonApplicationForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date
   *   The date service.
   * @param \Drupal\asu_application\Service\SalespersonApplicationProjectProvider $projectProvider
   *   Project label provider for the form.
   * @param \Drupal\asu_application\Service\LateApplicationChecker $lateApplicationChecker
   *   Late application detector.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $date,
    protected SalespersonApplicationProjectProvider $projectProvider,
    protected LateApplicationChecker $lateApplicationChecker,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('asu_application.salesperson_project_provider'),
      $container->get('asu_application.late_application_checker'),
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
  public function buildForm(array $form, FormStateInterface $form_state, ?string $user_id = NULL, ?string $project_id = NULL) {
    if ($user_id) {
      $projects = [];
      $user = $this->entityTypeManager->getStorage('user')->load($user_id);
      try {
        $projects = $this->projectProvider->getSelectableProjects();
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
        $label = $project['title'];
        if (!empty($project['address'])) {
          $label .= ', ' . $project['address'];
        }
        $options[$key] = $label;
        $ownership[$key] = (isset($project['ownership_type'])) ? strtolower($project['ownership_type']) : NULL;
      }

      $form['user'] = [
        '#markup' => sprintf('<h3>%s: %s</h3>', $this->t('User'), $user->getEmail()),
      ];

      $form['user_applications_title'] = [
        '#markup' => sprintf('<h4>%s</h4>', $this->t('User applications for active projects')),
      ];

      if (!empty($userApplications)) {
        $missing_project_ids = [];
        foreach ($userApplications as $application) {
          /** @var \Drupal\asu_application\Entity\Application $application */
          $application_project_id = (int) $application->getProjectId();
          if ($application_project_id && !isset($projects[$application_project_id])) {
            $missing_project_ids[$application_project_id] = $application_project_id;
          }
        }
        if ($missing_project_ids) {
          $projects += $this->projectProvider->loadProjectLabels($missing_project_ids);
        }

        foreach ($userApplications as $key => $application) {
          /** @var \Drupal\asu_application\Entity\Application $application */
          $status = $application->isLocked() ? $this->t('Already sent') : $this->t('Draft');
          $latest_change = $this->date->format($application->getLatestTimestamp(), 'long');
          $project = $projects[(int) $application->getProjectId()] ?? NULL;
          $project_label = $project['title'] ?? $this->t('Unknown project');
          if (!empty($project['address'])) {
            $project_label .= ', ' . $project['address'];
          }
          if ($this->lateApplicationChecker->isLateSubmission($application)) {
            $project_label .= ' — ' . $this->t('(after-application)');
          }
          $application_link = Link::fromTextAndUrl(
            $this->t('Edit'),
            $application->toUrl('edit-form'),
          );
          $form['user_applications_' . $key] = [
            '#markup' => $project_label . ' — ' . $status . ' (' . $latest_change . ') — '
            . $application_link->toString() . '<br>',
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
        '#empty_option' => $this->t('Select project'),
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

}
