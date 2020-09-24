<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\computed_field_plugin\Annotation\ComputedField;
use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\node\Entity\Node;

/**
 * Class StreetAddressField.
 *
 * @ComputedField(
 *   id = "field_apartment_address",
 *   label = @Translation("Street address"),
 *   type = "computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class StreetAddressField extends FieldItemList {

  use ComputedSingleItemTrait;

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
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a StreetAddressField object.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The data definition.
   * @param string $name
   *   (optional) The name of the created property, or NULL if it is the root
   *   of a typed data tree. Defaults to NULL.
   * @param \Drupal\Core\TypedData\TypedDataInterface $parent
   *   (optional) The parent object of the data property, or NULL if it is the
   *   root of a typed data tree. Defaults to NULL.
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->entityFieldManager = \Drupal::service('entity_field.manager');
    $this->configFactory = \Drupal::service('config.factory');
    $this->fieldTypeManager = \Drupal::service('plugin.manager.field.field_type');
    $this->logger = \Drupal::logger('reverse_entity_reference');
  }

  /**
   * Compute the street address value.
   *
   * @return mixed
   *   Returns the computed value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function singleComputeValue() {
    $current_entity = $this->getEntity();
    $reverse_references = $this->getReverseReferences();
    $value = FALSE;

    foreach ($reverse_references as $reference) {
      if (
        !empty($reference) &&
        $reference['referring_entity'] instanceof Node &&
        $this->getEntity()->hasField('field_apartment_number')
      ) {
        $referencing_node = $reference['referring_entity'];
        $value = $referencing_node->field_street_address->value . ' ' . $current_entity->field_apartment_number->value;
      }
    }

    return [
      'value' => $value,
    ];
  }

  /**
   * Load all the reverse references for this entity.
   *
   * @return array
   *   Returns referring entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getReverseReferences() {
    $reference_map = [];
    $entity = $this->getEntity();
    $entity_type = $entity->getEntityType();
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
        if ($entity_type->getBundleOf() == $target_type || $entity_type->id() == $target_type) {
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
    $referring_entities = [];
    $referring_entity_storage = $this->entityTypeManager->getStorage($referring_entity);

    foreach ($bundles as $referring_bundle) {
      $result = $this->doGetReferrers($referring_entity_storage, $field_name, $referring_bundle);
      if ($result) {
        foreach ($result as $referrer_id) {
          $node_storage = $this->entityTypeManager->getStorage('node');
          $referring_entities[] = [
            'referring_entity_type' => $referring_entity,
            'field_name' => $field_name,
            'referring_entity_id' => $referrer_id,
            'referring_entity' => $node_storage->load($referrer_id)
          ];
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
          ->condition($field_name, $this->getEntity()->id())
          ->execute();
      }
      else {
        $result = $referring_entity_storage->getQuery()
          ->condition($field_name, $this->getEntity()->id())
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
