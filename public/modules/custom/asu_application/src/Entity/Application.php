<?php

namespace Drupal\asu_application\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\Entity\User;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Application entity.
 *
 * @ContentEntityType(
 *   id = "asu_application",
 *   label = @Translation("Application"),
 *   base_table = "asu_application",
 *   revision_table = "asu_application_revision",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "bundle",
 *     "owner" = "uid",
 *     "revision" = "vid",
 *     "published" = "status",
 *     "created" = "created",
 *     "changed" = "changed",
 *   },
 *   fieldable = TRUE,
 *   admin_permission = "administer content",
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\asu_application\ApplicationListBuilder",
 *     "access" = "Drupal\asu_application\Entity\Access\ApplicationEntityAccess",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\asu_application\Form\ApplicationForm",
 *       "add" = "Drupal\asu_application\Form\ApplicationForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/application/{asu_application}",
 *     "add-page" = "/application/add",
 *     "add-form" = "/application/add/{application_type}/{project_id}",
 *     "edit-form" = "/application/{asu_application}/edit",
 *     "delete-form" = "/application/{asu_application}/delete",
 *     "collection" = "/admin/content/application",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   bundle_entity_type = "application_type",
 *   field_ui_base_route = "entity.application_type.edit_form",
 * )
 */
class Application extends EditorialContentEntityBase implements ContentEntityInterface, EntityOwnerInterface {
  use EntityOwnerTrait;

  /**
   * Gets project id.
   *
   * @return string
   *   Project id.
   */
  public function getProjectId() {
    return $this->project_id->value;
  }

  /**
   * Return has children.
   *
   * @return bool
   *   Has children.
   */
  public function getHasChildren(): bool {
    return $this->has_children->value ?? FALSE;
  }

  /**
   * Return is new permit number.
   *
   * @return bool
   *   Has children.
   */
  public function hasNewPermitNumber(): bool {
    $permitValue = $this->field_is_new_permit_number->value ?? TRUE;

    if ($permitValue || $permitValue == '1') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get apartments.
   *
   * @return object
   *   Apartments object
   */
  public function getApartments(): object {
    return $this->apartment;
  }

  /**
   * Get the ids of the apartments in application.
   *
   * @return array
   *   Array of ids.
   */
  public function getApartmentIds(): array {
    $apartments = [];
    foreach ($this->getApartments() as $apartment) {
      $apartments[] = (int) $apartment->id;
    }
    return $apartments;
  }

  /**
   * Get main applicants.
   *
   * @return array
   *   Array of main applicants.
   */
  public function getMainApplicant(): ?array {
    if ($this->hasField('main_applicant')) {
      return $this->main_applicant->getValue() ?? [];
    }
    return NULL;
  }

  /**
   * Get additional applicants.
   *
   * @return array
   *   Array of applicants.
   */
  public function getAdditionalApplicants(): array {
    return $this->applicant->getValue() ?? [];
  }

  /**
   * Is additional applicant set to the application form.
   *
   * @return bool
   *   Does the application have additional applicants.
   */
  public function hasAdditionalApplicant(): bool {
    return !$this->applicant->isEmpty();
  }

  /**
   * Application has been sent to backend and therefore is locked.
   *
   * @return bool
   *   Application has been sent.
   */
  public function isLocked(): bool {
    return (bool) $this->field_locked->value;
  }

  /**
   * Application last timestamp.
   *
   * @return int
   *   Application updated or sent timestamp.
   */
  public function getLatestTimestamp(): int {
    if (!reset($this->values["create_to_django"])) {
      return reset($this->values["changed"]);
    }

    return reset($this->values["create_to_django"]);
  }

  /**
   * Has there been an error while sending api request to backend.
   *
   * @return bool
   *   Application has error.
   */
  public function hasError(): bool {
    return (bool) $this->error->value;
  }

  /**
   * Get error message.
   *
   * @return string
   *   Error text.
   */
  public function getError(): string {
    return $this->error->value;
  }

  /**
   * {@inheritDoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Contact entity.'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the application entity.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden',
        'region' => 'hidden',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Change to entity reference.
    $fields['parent_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Application parent'))
      ->setDescription(t('The parent application of application entity.'))
      ->setReadOnly(TRUE);

    $fields['project_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Project ID'))
      ->setDescription(t('The id of the project'))
      ->setReadOnly(TRUE);

    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Project'))
      ->setDescription(t('The project'))
      ->setSettings([
        'target_type' => 'node',
        'handler_settings' => [
          'target_bundles' => ['project'],
        ],
      ]);

    $fields['apartment'] = BaseFieldDefinition::create('asu_apartment')
      ->setCardinality(-1)
      ->setReadOnly(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'formatter',
        'type' => 'author',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'asu_apartment_widget',
        'weight' => 5,
        'settings' => [],
      ]);

    $fields['field_backend_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Backend application ID'))
      ->setDescription(t('Application UUID returned from Django backend.'))
      ->setReadOnly(TRUE)
      ->setDefaultValue(NULL);

    $fields['main_applicant'] = BaseFieldDefinition::create('asu_main_applicant')
      ->setLabel(t('Basic information'))
      ->setDescription(t('Basic information of the people who are applying'))
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'asu_main_applicant_widget',
        'weight' => 5,
        'settings' => [],
      ]);

    $fields['applicant'] = BaseFieldDefinition::create('asu_applicant')
      ->setLabel(t('Applicants'))
      ->setDescription(t('Basic information of the people who are part of the application'))
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'asu_applicant_widget',
        'weight' => 5,
        'settings' => [],
      ]);

    $fields['has_children'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('I have underage children who are moving in with me'))
      ->setDisplayOptions('form', [
        'type' => 'asu_applicant_widget',
        'weight' => 5,
        'settings' => [],
      ]);

    $fields['created_admin'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Created by admin'))
      ->setDescription(t('A boolean indicating whether application is created by admin.'))
      ->setDefaultValue(FALSE);

    $fields['created_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The creator ID of author of the application entity.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['create_to_django'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Created to Django'))
      ->setDescription(t('A datetime value when application is sent to Django.'))
      ->setReadOnly(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['field_locked'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Locked'))
      ->setDefaultValue(0)
      ->setReadOnly(TRUE);

    $fields['error'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Error'))
      ->setDefaultValue('');

    return $fields;
  }

  /**
   * If sales creates application for customer, use user_id query parameter.
   *
   * @param Drupal\Core\Entity\EntityStorageInterface $storage
   *   Entity storage interface.
   * @param array $values
   *   Entity values.
   *
   * @throws \Exception
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    // @todo muista jotain. ei saa ajaa jos asko.
    parent::preCreate($storage, $values);

    $parameters = \Drupal::routeMatch()->getParameters();
    $project_id = $parameters->get('project_id');

    $user = User::load(\Drupal::currentUser()->id());
    if ($user->bundle() == 'sales') {
      $created_admin = TRUE;

      if (\Drupal::request()->get('user_id')) {
        $user_id = \Drupal::request()->get('user_id');
      }
      else {
        throw new \Exception('Tried to create new application without user.');
      }
    }
    else {
      $user_id = $user->id();
      $created_admin = FALSE;
    }

    $values += [
      'uid' => $user_id,
      'project_id' => $project_id,
      'project' => $project_id,
      'created_admin' => $created_admin,
      'created_by' => $user->id(),
      'create_to_django' => NULL,
    ];

  }

  /**
   * Clean sensitive applicant information from application.
   */
  public function cleanSensitiveInformation(): void {
    if ($this->hasField('main_applicant')) {
      // Clear main applicant information.
      $this->set('main_applicant', NULL);
    }

    if ($this->hasField('applicant')) {
      // Clear sub applicant information.
      $this->set('applicant', NULL);
    }

    // Save application changes.
    $this->save();
  }

}
