<?php

namespace Drupal\asu_content\Form;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Form which allows bulk adding images to apartments.
 */
class BulkEditForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'asu_content_bulk_edit_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $id = NULL) {
    // Build the form for route without a project id.
    if (!$id) {
      $projects = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
          'type' => 'project',
          'status' => 1,
        ]);
      $options = [$this->t('Select')];
      foreach ($projects as $project) {
        $options[$project->id()] = $project->title->value;
      }

      $form['project'] = [
        '#type' => 'select',
        '#options' => $options,
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Select'),
      ];
      return $form;
    }

    // Build the form for route with a project id.
    $project = Node::load($id);
    $apartments = $project->field_apartments->referencedEntities();

    $form['floorplan'] = [
      '#type' => 'managed_file',
      '#title' => t('Floorplan'),
      '#upload_location' => 'public://',
      '#multiple' => FALSE,
    ];
    $form['images'] = [
      '#title' => t('Images'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#required' => FALSE,
      '#multiple' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg gif png'],
      ],
    ];

    $options = [];
    foreach ($apartments as $apartment) {
      $title = '';
      $title .= $apartment->title->value . ' - ';
      $title .= $apartment->field_apartment_structure->value . ' - ';
      $title .= 'krs: ' . $apartment->field_floor->value;
      $options[$apartment->id()] = $title;
    }
    $form['apartments'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add images'),
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();

    // Handling the project selection.
    if (isset($values['project'])) {
      $url = \Drupal::urlGenerator()
        ->generateFromRoute(
        'asu_content.bulk_edit',
        ['id' => $values['project']],
        ['absolute' => TRUE]
      );
      (new RedirectResponse($url))->send();
      return $form;
    }

    // Handling the file uploads.
    $updated = [];
    foreach ($values['apartments'] as $key => $value) {
      if ($key == $value) {
        /** @var \Drupal\node\Entity\Node $apartment */
        $apartment = Node::load($value);
        if (!empty($values['floorplan']) && isset($values['floorplan'][0])) {
          $apartment->set('field_floorplan', ['target_id' => $values['floorplan'][0]]);
        }

        if (!empty($values['images'])) {
          $images = [];
          foreach ($values['images'] as $imageId) {
            $images[] = ['target_id' => $imageId];
          }
          $apartment->set('field_images', array_merge($apartment->get('field_images')->getValue(), $images));
        }

        $apartment->save();
        $updated[] = $apartment->id();
      }
    }
    \Drupal::messenger()->addMessage(count($updated) . ' ' . $this->t('apartments updated.'));
  }

}
