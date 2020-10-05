<?php

namespace Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory;

use Drupal\file\Entity\File;
use Drupal\search_api\IndexInterface;
use Drupal\elasticsearch_connector\Event\PrepareIndexEvent;
use Drupal\elasticsearch_connector\Event\PrepareIndexMappingEvent;
use Drupal\elasticsearch_connector\Event\BuildIndexParamsEvent;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api_autocomplete\Suggester\SuggesterInterface;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\taxonomy\Entity\Term;

/**
 * Create Elasticsearch Indices.
 */
class IndexFactory {

  /**
   * Build parameters required to index.
   *
   * TODO: We need to handle the following params as well:
   * ['consistency'] = (enum) Explicit write consistency setting for the
   * operation
   * ['refresh']     = (boolean) Refresh the index after performing the
   * operation
   * ['replication'] = (enum) Explicitly set the replication type
   * ['fields']      = (list) Default comma-separated list of fields to return
   * in the response for updates.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index to create.
   *
   * @return array
   *   Associative array with the following keys:
   *   - index: The name of the index on the Elasticsearch server.
   *   - type(optional): The name of the type to use for the given index.
   */
  public static function index(IndexInterface $index) {
    $params = [];
    $params['index'] = static::getIndexName($index);
    return $params;
  }

  /**
   * Build parameters required to create an index
   * TODO: Add the timeout option.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return array
   */
   public static function create(IndexInterface $index) {
     $indexName = static::getIndexName($index);
     $indexConfig =  [
       'index' => $indexName,
       'body' => [
         'settings' => [
           'number_of_shards' => $index->getOption('number_of_shards', 5),
           'number_of_replicas' => $index->getOption('number_of_replicas', 1),
         ],
       ],
     ];

     // Allow other modules to alter index config before we create it.
     $dispatcher = \Drupal::service('event_dispatcher');
     $prepareIndexEvent = new PrepareIndexEvent($indexConfig, $indexName);
     $event = $dispatcher->dispatch(PrepareIndexEvent::PREPARE_INDEX, $prepareIndexEvent);
     $indexConfig = $event->getIndexConfig();

     return $indexConfig;
   }

  /**
   * Build parameters to bulk delete indexes.
   *
   * @param \Drupal\search_api\IndexInterface $index
   * @param array $ids
   *
   * @return array
   */
  public static function bulkDelete(IndexInterface $index, array $ids) {
    $params = IndexFactory::index($index);
    foreach ($ids as $id) {
      $params['body'][] = [
        'delete' => [
          '_index' => $params['index'],
          '_id' => $id,
        ],
      ];
    }

    return $params;
  }

  /**
   * Build parameters to bulk delete indexes.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array of items to be indexed, keyed by their item IDs.
   *
   * @return array
   *   Array of parameters to send along to Elasticsearch to perform the bulk
   *   index.
   */
  public static function bulkIndex(IndexInterface $index, array $items) {
    $params = static::index($index);

    foreach ($items as $id => $item) {
      $data = [
        '_language' => $item->getLanguage(),
      ];
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($item as $name => $field) {
        $value = NULL;

        if (!empty($field->getValues())) {
          $cardinality = $field->getDataDefinition()
            ->getFieldDefinition()
            ->getFieldStorageDefinition()
            ->getCardinality();

          $field_type = $field->getDataDefinition()->getFieldDefinition()->getType();

          // Single is indexed as a string.
          if ($cardinality == 1) {
            if('list_string' == $field_type) {
              $value = $field->getDataDefinition()->getSetting('allowed_values')[reset($field->getValues())];
            } else if( 'entity_reference' == $field_type){
              $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load(reset($field->getValues()));
              $value = $term->getName();
            } else if('image' == $field_type) {
              if($file = File::load(reset($field->getValues()))){
                $value = $file->createFileUrl(FALSE);
              }
            }
            else {
              $val = reset($field->getValues());
              $value = count($val) > 1 ? $val['name'] : $val;
            }
          }
          // Field with multiple values are indexed as array of strings.
          else {
            // We are dealing with entity reference
            if( 'entity_reference' == $field->getDataDefinition()->getFieldDefinition()->getType() ) {
              #here
              foreach($field->getValues() as $val){
                if(is_array($val)) {
                  $value[] = $val['name'];
                } else {
                  $term = Term::load($val);
                  $value[] = $term->getName();
                }
              }
            }
            elseif('image' == $field_type) {
              foreach($field->getValues() as $val){
                if($file = File::load($val)){
                  $value[] = $file->createFileUrl(FALSE);
                }
              }
            }
            else {
              $value = $field->getValues();
            }
          }
        }
        $data[$field->getFieldIdentifier()] = $value;
      }
      $params['body'][] = ['index' => ['_id' => $id]];
      $params['body'][] = $data;
    }

    // Allow other modules to alter index params before we send them.
    $indexName = IndexFactory::getIndexName($index);
    $dispatcher = \Drupal::service('event_dispatcher');
    $buildIndexParamsEvent = new BuildIndexParamsEvent($params, $indexName);
    $event = $dispatcher->dispatch(BuildIndexParamsEvent::BUILD_PARAMS, $buildIndexParamsEvent);
    $params = $event->getElasticIndexParams();

    return $params;
  }

  /**
   * Build parameters required to create an index mapping.
   *
   * TODO: We need also:
   * $params['index'] - (Required)
   * ['type'] - The name of the document type
   * ['timeout'] - (time) Explicit operation timeout.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   *
   * @return array
   *   Parameters required to create an index mapping.
   */
  public static function mapping(IndexInterface $index) {
    $params = static::index($index);

    $properties = [
      'id' => [
        'type' => 'keyword',
        'index' => 'true',
      ],
    ];

    // Figure out which fields are used for autocompletion if any.
    if (\Drupal::moduleHandler()->moduleExists('search_api_autocomplete')) {
      $autocompletes = \Drupal::entityTypeManager()->getStorage('search_api_autocomplete_search')->loadMultiple();
      $all_autocompletion_fields = [];
      foreach ($autocompletes as $autocomplete) {
        $suggester = \Drupal::service('plugin.manager.search_api_autocomplete.suggester');
        $plugin = $suggester->createInstance('server', ['#search' => $autocomplete]);
        assert($plugin instanceof SuggesterInterface);
        $configuration = $plugin->getConfiguration();
        $autocompletion_fields = isset($configuration['fields']) ? $configuration['fields'] : [];
        if (!$autocompletion_fields) {
          $autocompletion_fields = $plugin->getSearch()->getIndex()->getFulltextFields();
        }

        // Collect autocompletion fields in an array keyed by field id.
        $all_autocompletion_fields += array_flip($autocompletion_fields);
      }
     }

    // Map index fields.
    foreach ($index->getFields() as $field_id => $field_data) {
      $properties[$field_id] = MappingFactory::mappingFromField($field_data);
      // Enable fielddata for fields that are used with autocompletion.
      if (isset($all_autocompletion_fields[$field_id])) {
        $properties[$field_id]['fielddata'] = TRUE;
      }
    }

    $properties['_language'] = [
      'type' => 'keyword',
    ];

    $params['body']['properties'] = $properties;

    // Allow other modules to alter index mapping before we create it.
    $dispatcher = \Drupal::service('event_dispatcher');
    $prepareIndexMappingEvent = new PrepareIndexMappingEvent($params, $params['index']);
    $event = $dispatcher->dispatch(PrepareIndexMappingEvent::PREPARE_INDEX_MAPPING, $prepareIndexMappingEvent);
    $params = $event->getIndexMappingParams();

    return $params;
  }

  /**
   * Helper function. Returns the Elasticsearch name of an index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   Index object.
   *
   * @return string
   *   The name of the index on the Elasticsearch server. Includes a prefix for
   *   uniqueness, the database name, and index machine name.
   */
  public static function getIndexName(IndexInterface $index) {

    // Get index machine name.
    $index_machine_name = is_string($index) ? $index : $index->id();

    // Get prefix and suffix from the cluster if present.
    $cluster_id = $index->getServerInstance()->getBackend()->getCluster();
    $cluster_options = Cluster::load($cluster_id)->options;

    $index_suffix = '';
    if (!empty($cluster_options['rewrite']['rewrite_index'])) {
      $index_prefix = isset($cluster_options['rewrite']['index']['prefix']) ? $cluster_options['rewrite']['index']['prefix'] : '';
      if ($index_prefix && substr($index_prefix, -1) !== '_') {
        $index_prefix .= '_';
      }
      $index_suffix = isset($cluster_options['rewrite']['index']['suffix']) ? $cluster_options['rewrite']['index']['suffix'] : '';
      if ($index_suffix && $index_suffix[0] !== '_') {
        $index_suffix = '_' . $index_suffix;
      }
    }
    else {
      // If a custom rewrite is not enabled, set prefix to db name by default.
      $options = \Drupal::database()->getConnectionOptions();
      $index_prefix = 'elasticsearch_index_' . $options['database'] . '_';
    }

    return strtolower(preg_replace(
      '/[^A-Za-z0-9_]+/',
      '',
      $index_prefix . $index_machine_name . $index_suffix
    ));
  }

  /**
   * Helper function. Returns the elasticsearch value for a given field.
   *
   * @param string $field_type
   * @param mixed $value
   *
   * @return string
   */
  protected static function getFieldValue($field_type, $raw) {
    switch ($field_type) {
      case 'string':
        $value = (string) $raw;
        break;

      case 'text':
        $value = $raw->toText();
        break;

      default:
        $value = $raw;
    }
    return $value;
  }


  /**
   * Helper function. Returns true if the field is a list of values.
   *
   * @param \Drupal\search_api\IndexInterface $index
   * @param \Drupal\search_api\Item\Field $field
   *
   * @return bool
   */
  protected static function isFieldList($index, $field) {
    $is_list = FALSE;

    // Ensure we get the field definition for the root/parent field item (ie tags).
    $property_definitions =  $index->getPropertyDefinitions($field->getDatasourceId());
    $root_property = Utility::splitPropertyPath($field->getPropertyPath(), FALSE)[0];
    $field_definition = $property_definitions[$root_property];

    // Using $field_definition->isList() doesn't seem to be accurate, so we
    // check the fieldStorage cardinality !=1.
    if (method_exists($field_definition, 'getFieldStorageDefinition')) {
      $storage = $field_definition->getFieldStorageDefinition();
      if (1 != $storage->getCardinality()) {
        $is_list = TRUE;
      }
    }
    return $is_list;
  }
}
