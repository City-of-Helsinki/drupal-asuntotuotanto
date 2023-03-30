<?php

namespace Drupal\asu_content;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\node\Entity\Node;

/**
 * Class StreetAddressField.
 */
class CollectReverseEntity {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Current entity.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $currentEntity;

  /**
   * Constructs a StreetAddressField object.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, FieldTypePluginManagerInterface $fieldTypeManager, LoggerChannel $logger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->fieldTypeManager = $fieldTypeManager;
    $this->logger = $logger;
  }

  /**
   * Load all the reverse references for this entity.
   *
   * @param \Drupal\node\Entity\Node $entity
   *   Node to handle.
   *
   * @return array
   *   Returns referring entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getReverseReferences(Node $entity) {
    $reference_map = [];
    $this->currentEntity = $entity;
    $referring_bundle = 'project';

    $entities = $this->entityFieldManager->getFieldMapByFieldType('entity_reference');

    if (array_key_exists('node', $entities)) {
      $referring_node = $entities['node'];
      $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions('node');

      foreach ($referring_node as $field_name => $field) {

        // Get field storage definition if available.
        if (!isset($field_definitions[$field_name])) {
          continue;
        }

        $field_definition = $field_definitions[$field_name];
        $target_type = $field_definition->getSetting('target_type');

        // Check if this entity type and bundle is a target of this field.
        if (
          $this->currentEntity->getEntityType()->getBundleOf() == $target_type ||
          $this->currentEntity->getEntityType()->id() == $target_type
        ) {
          if (
            $this->entityTypeManager->getDefinition('node')->getKey('id') &&
            in_array($referring_bundle, $field['bundles'])
          ) {
            $reference_map = array_merge($reference_map, $this->getReferrers('node', $field_name, $field['bundles']));
          }
        }
      }
    }

    return $reference_map;
  }

  /**
   * Referrers getter.
   *
   * Get all the entities referring this entity given an entity type and field
   * name.
   *
   * @param string $referring_entity
   *   The referring entity type.
   * @param string $field_name
   *   The referring field on that entity type.
   * @param string[] $bundles
   *   (optional) The bundles that use the referring field. Defaults to
   *   array(NULL).
   *
   * @return array
   *   Returns referring entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getReferrers($referring_entity, $field_name, array $bundles = [NULL]) {
    $ids = &drupal_static(__FUNCTION__);
    $referring_entities = [];
    $referring_entity_storage = $this->entityTypeManager->getStorage($referring_entity);

    foreach ($bundles as $referring_bundle) {
      $result = $this->doGetReferrers($referring_entity_storage, $field_name, $referring_bundle);
      $id = reset($result);
      if (isset($ids[$id])) {
        $referring_entities = $ids[$id];
      }
      if ($result) {
        foreach ($result as $referrer_id) {
          $node_storage = $this->entityTypeManager->getStorage('node');
          $referring_entities[] = [
            'referring_entity_type' => $referring_entity,
            'field_name' => $field_name,
            'referring_entity_id' => $referrer_id,
            'referring_entity' => $node_storage->load($referrer_id),
          ];
          $ids[$referrer_id] = $referring_entities;
        }
      }
    }
    return $referring_entities;
  }

  /**
   * Referrers helper getter.
   *
   * Get all the entities referring this entity given an entity type and field
   * name.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $referring_entity_storage
   *   The storage class for the referring entity type.
   * @param string $field_name
   *   The name of the field used to refer to this entity type.
   * @param string|null $referring_bundle
   *   (optional) The bundle that of the referring entity type that
   *   can reference the referred entity.
   *
   * @return int[]|false
   *   Returns an array of entity ids or false.
   */
  protected function doGetReferrers(EntityStorageInterface $referring_entity_storage, $field_name, $referring_bundle = NULL) {
    $result = FALSE;

    try {
      if (isset($referring_bundle)) {
        $result = $referring_entity_storage->getQuery()
          ->condition('type', $referring_bundle)
          ->condition($field_name, $this->currentEntity->id())
          ->execute();
      }
      else {
        $result = $referring_entity_storage->getQuery()
          ->condition($field_name, $this->currentEntity->id())
          ->execute();
      }

    }
    catch (QueryException $e) {
      $this->logger->error(
        "Something went wrong with querying the DB for reverse references. Field Type: @field_type Entity Type: @entity_type Bundle: @bundle PHP Exception: @exception",
        [
          "@field_type" => $field_name,
          "@entity_type" => $referring_entity_storage->getEntityTypeId(),
          "@bundle" => ($referring_bundle ?: "all"),
          "@exception" => $e->getMessage(),
        ]
      );
    }
    return $result;
  }

}
