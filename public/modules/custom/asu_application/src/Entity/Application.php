<?php

namespace Drupal\asu_application\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
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
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
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
   * Get additional applicants.
   *
   * @return array
   *   Array of applicants.
   */
  public function getApplicants(): array {
    return $this->applicant->getValue() ?? [];
  }

  /**
   * Is additional applicant set to the application form.
   *
   * @return bool
   *   Does the application have additional applicants.
   */
  public function hasAdditionalApplicant(): bool {
    return $this->applicant->isEmpty() ? FALSE : TRUE;
  }

  /**
   * Application has been sent to backend and therefore is locked.
   *
   * @return bool
   *   Application has been sent.
   */
  public function isLocked(): bool {
    return $this->field_locked->value ? TRUE : FALSE;
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
   * If salesperson creates application on behalf of customer.
   * Get the user id query parameter.
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);

    $parameters = \Drupal::routeMatch()->getParameters();
    $project_id = $parameters->get('project_id');

    $user = User::load(\Drupal::currentUser()->id());
    if ($user->bundle() == 'sales') {
      if (\Drupal::request()->get('user_id')) {
        $user_id = \Drupal::request()->get('user_id');
      }
      else {
        throw new \Exception('Tried to create new application without user.');
      }
    }
    else {
      $user_id = $user->id();
    }

    $values += [
      'uid' => $user_id,
      'project_id' => $project_id,
    ];

  }

}
