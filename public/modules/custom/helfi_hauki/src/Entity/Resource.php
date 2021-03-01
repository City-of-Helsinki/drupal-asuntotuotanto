<?php

declare(strict_types = 1);

namespace Drupal\helfi_hauki\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;

/**
 * Defines the hauki_resource entity class.
 *
 * @ContentEntityType(
 *   id = "hauki_resource",
 *   label = @Translation("Hauki - Resource"),
 *   label_collection = @Translation("Hauki - Resource"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "storage" = "Drupal\helfi_hauki\Entity\Storage\ResourceStorage",
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
 *   base_table = "hauki_resource",
 *   data_table = "hauki_resource_field_data",
 *   revision_table = "hauki_resource_revision",
 *   revision_data_table = "hauki_resource_field_revision",
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
 *     "canonical" = "/hauki-resource/{hauki_resource}",
 *     "edit-form" = "/admin/content/hauki-resource/{hauki_resource}/edit",
 *     "delete-form" = "/admin/content/hauki-resource/{hauki_resource}/delete",
 *     "collection" = "/admin/content/hauki-resource"
 *   },
 *   field_ui_base_route = "hauki_resource.settings"
 * )
 */
final class Resource extends RemoteEntityBase {

  use RevisionLogEntityTrait;

  /**
   * Adds the given data source.
   *
   * @param string $source_type_id
   *   The source type id (for example tprek).
   * @param string $origin_id
   *   The origin id (for example miscinfo-12345).
   *
   * @return $this
   *   The self.
   */
  public function addOrigin(string $source_type_id, string $origin_id) : self {
    if (!$this->hasOrigin($origin_id)) {
      $this->get('origins')->appendItem([
        'key' => $source_type_id,
        'value' => $origin_id,
      ]);
    }
    return $this;
  }

  /**
   * Gets the origins.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The origins.
   */
  public function getOrigins() : FieldItemListInterface {
    return $this->get('origins');
  }

  /**
   * Removes the given source.
   *
   * @param string $origin
   *   The origin id.
   *
   * @return $this
   *   The self.
   */
  public function removeOrigin(string $origin) : self {
    $index = $this->getOriginIndex($origin);
    if ($index !== FALSE) {
      $this->get('origins')->offsetUnset($index);
    }
    return $this;
  }

  /**
   * Checks whether the source exists or not.
   *
   * @param string $origin
   *   The origin id.
   *
   * @return bool
   *   Whether we have given source or not.
   */
  public function hasOrigin(string $origin) : bool {
    return $this->getOriginIndex($origin) !== FALSE;
  }

  /**
   * Gets the index of the given origin.
   *
   * @param string $origin
   *   The origin id.
   *
   * @return int|bool
   *   The index of the given source, or FALSE if not found.
   */
  protected function getOriginIndex(string $origin) {
    $values = $this->get('origins')->getValue();
    $ids = array_map(function ($value) {
      return $value['key'];
    }, $values);

    return array_search($origin, $ids);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);

    $fields['resource_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Resource type'))
      ->setSettings([
        'is_ascii' => TRUE,
      ])
      ->setReadOnly(TRUE);

    $fields['origins'] = BaseFieldDefinition::create('key_value')
      ->setLabel(new TranslatableMarkup('Origins'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
