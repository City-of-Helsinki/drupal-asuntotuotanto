<?php

declare(strict_types = 1);

namespace Drupal\helfi_tpr\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the tpr_service entity class.
 *
 * @ContentEntityType(
 *   id = "tpr_service",
 *   label = @Translation("TPR - Service"),
 *   label_collection = @Translation("TPR - Service"),
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
 *     },
 *   },
 *   base_table = "tpr_service",
 *   data_table = "tpr_service_field_data",
 *   revision_table = "tpr_service_revision",
 *   revision_data_table = "tpr_service_field_revision",
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
 *     "canonical" = "/tpr-service/{tpr_service}",
 *     "edit-form" = "/admin/content/tpr-service/{tpr_service}/edit",
 *     "delete-form" = "/admin/content/tpr-service/{tpr_service}/delete",
 *     "collection" = "/admin/content/tpr-service"
 *   },
 *   field_ui_base_route = "tpr_service.settings"
 * )
 */
class Service extends TprEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function getMigration(): ?string {
    return 'tpr_service';
  }

  /**
   * Gets the data.
   *
   * @param string $key
   *   The key.
   * @param null|mixed $default
   *   The default value.
   *
   * @return mixed|null
   *   The data.
   */
  public function getData(string $key, $default = NULL) {
    $data = [];
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
    }
    return isset($data[$key]) ? $data[$key] : $default;
  }

  /**
   * Sets the data.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   *   The self.
   */
  public function setData(string $key, $value) : self {
    $this->get('data')->__set($key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['description'] = BaseFieldDefinition::create('text_with_summary')
      ->setTranslatable(TRUE)
      ->setLabel(new TranslatableMarkup('Description'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Data'))
      ->setDescription(new TranslatableMarkup('A serialized array of additional data.'));

    return $fields;
  }

}
