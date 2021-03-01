<?php

declare(strict_types = 1);

namespace Drupal\helfi_tpr\Entity;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the tpr_unit entity class.
 *
 * @ContentEntityType(
 *   id = "tpr_unit",
 *   label = @Translation("TPR - Unit"),
 *   label_collection = @Translation("TPR - Unit"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\helfi_tpr\Entity\Listing\ListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\helfi_api_base\Entity\Routing\EntityRouteProvider",
 *     }
 *   },
 *   base_table = "tpr_unit",
 *   data_table = "tpr_unit_field_data",
 *   revision_table = "tpr_unit_revision",
 *   revision_data_table = "tpr_unit_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "uid" = "uid",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   },
 *   revision_metadata_keys = {
 *     "revision_created" = "revision_timestamp",
 *     "revision_user" = "revision_user",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/tpr-unit/{tpr_unit}",
 *     "edit-form" = "/admin/content/tpr-unit/{tpr_unit}/edit",
 *     "delete-form" = "/admin/content/tpr-unit/{tpr_unit}/delete",
 *     "collection" = "/admin/content/tpr-unit",
 *   },
 *   field_ui_base_route = "tpr_unit.settings"
 * )
 */
class Unit extends TprEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function getMigration(): ?string {
    return 'tpr_unit';
  }

  /**
   * Adds the given service.
   *
   * @param \Drupal\helfi_tpr\Entity\Service $service
   *   The service.
   *
   * @return $this
   *   The self.
   */
  public function addService(Service $service) : self {
    if (!$this->hasService($service)) {
      $this->get('services')->appendItem($service);
    }
    return $this;
  }

  /**
   * Removes the given service.
   *
   * @param \Drupal\helfi_tpr\Entity\Service $service
   *   The service.
   *
   * @return $this
   *   The self.
   */
  public function removeService(Service $service) : self {
    $index = $this->getServiceIndex($service);
    if ($index !== FALSE) {
      $this->get('services')->offsetUnset($index);
    }
    return $this;
  }

  /**
   * Checks whether the service exists or not.
   *
   * @param \Drupal\helfi_tpr\Entity\Service $service
   *   The service.
   *
   * @return bool
   *   Whether we have given service or not.
   */
  public function hasService(Service $service) : bool {
    return $this->getServiceIndex($service) !== FALSE;
  }

  /**
   * Gets the index of the given service.
   *
   * @param \Drupal\helfi_tpr\Entity\Service $service
   *   The service.
   *
   * @return int|bool
   *   The index of the given service, or FALSE if not found.
   */
  protected function getServiceIndex(Service $service) {
    $values = $this->get('services')->getValue();
    $ids = array_map(function ($value) {
      return $value['target_id'];
    }, $values);

    return array_search($service->id(), $ids);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['phone'] = static::createStringField('Phone', BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setTranslatable(FALSE);
    $fields['call_charge_info'] = static::createStringField('Call charge info');
    $fields['email'] = static::createStringField('Email')
      ->setTranslatable(FALSE);
    $fields['accessibility_phone'] = static::createStringField('Accessibility phone')
      ->setTranslatable(FALSE);
    $fields['accessibility_email'] = static::createStringField('Accessibility email')
      ->setTranslatable(FALSE);
    $fields['www'] = static::createLinkField('Website link');
    $fields['accessibility_www'] = static::createLinkField('Accessibility website link')
      ->setTranslatable(FALSE);
    $fields['description'] = BaseFieldDefinition::create('text_with_summary')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Description'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['address'] = BaseFieldDefinition::create('address')
      ->setLabel(new TranslatableMarkup('Address'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('field_overrides', [
        AddressField::GIVEN_NAME => ['override' => FieldOverride::HIDDEN],
        AddressField::ADDITIONAL_NAME => ['override' => FieldOverride::HIDDEN],
        AddressField::FAMILY_NAME => ['override' => FieldOverride::HIDDEN],
        AddressField::ORGANIZATION => ['override' => FieldOverride::HIDDEN],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['address_postal'] = static::createStringField('Address postal');
    $fields['service_map_embed'] = static::createStringField('Service map embed')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'service_map_embed',
        'weight' => 0,
      ]);
    $fields['latitude'] = static::createStringField('Latitude')
      ->setTranslatable(FALSE);
    $fields['longitude'] = static::createStringField('Longitude')
      ->setTranslatable(FALSE);
    $fields['streetview_entrance_url'] = static::createLinkField('Streetview entrance')
      ->setTranslatable(FALSE);
    $fields['services'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Services'))
      ->setSettings([
        'target_type' => 'tpr_service',
        'handler_settings' => [
          'target_bundles' => ['tpr_service'],
        ],
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
