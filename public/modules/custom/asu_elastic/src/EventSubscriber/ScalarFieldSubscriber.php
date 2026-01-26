<?php

namespace Drupal\asu_elastic\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\search_api\SearchApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts configured single-value fields from arrays into scalars.
 */
class ScalarFieldSubscriber implements EventSubscriberInterface {

  /**
   * Entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Allowed scalar field types keyed for fast lookups.
   *
   * @var array<string, bool>
   */
  protected array $scalarTypeWhitelist;

  /**
   * Global list of fields that must be scalar regardless of type.
   *
   * @var array<string, bool>
   */
  protected array $forcedScalarFields;

  /**
   * Fields that must remain multi-value even if the type is scalar.
   *
   * @var array<string, bool>
   */
  protected array $forcedMultiFields;

  /**
   * Cached scalar field names per Elasticsearch index.
   *
   * @var array<string, array<string, bool>>|null
   */
  protected ?array $scalarFieldMap = NULL;

  /**
   * ScalarFieldSubscriber constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, array $scalarTypeWhitelist = [], array $forcedScalarFields = [], array $forcedMultiFields = []) {
    $this->entityTypeManager = $entityTypeManager;
    $this->scalarTypeWhitelist = array_fill_keys($scalarTypeWhitelist, TRUE);
    $this->forcedScalarFields = array_fill_keys($forcedScalarFields, TRUE);
    $this->forcedMultiFields = array_fill_keys($forcedMultiFields, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      IndexParamsEvent::class => 'onIndexParams',
    ];
  }

  /**
   * Normalizes configured fields right before the bulk request is sent.
   */
  public function onIndexParams(IndexParamsEvent $event): void {
    $params = $event->getParams();
    if (empty($params['body']) || !is_array($params['body'])) {
      return;
    }

    $scalarFields = $this->getScalarFieldsForIndex($event->getIndexName());
    if (!$scalarFields) {
      return;
    }

    $bodyCount = count($params['body']);
    for ($i = 0; $i < $bodyCount; $i += 2) {
      if (!isset($params['body'][$i + 1]) || !is_array($params['body'][$i + 1])) {
        continue;
      }
      foreach ($params['body'][$i + 1] as $fieldName => $value) {
        if (!isset($scalarFields[$fieldName])) {
          continue;
        }
        $params['body'][$i + 1][$fieldName] = $this->normalizeValue($value);
      }
    }

    $event->setParams($params);
  }

  /**
   * Retrieves scalar field definitions for the provided index name.
   */
  protected function getScalarFieldsForIndex(string $indexName): array {
    $this->warmScalarFieldMap();

    $fields = $this->scalarFieldMap[$indexName] ?? [];
    if ($this->forcedScalarFields) {
      $fields += $this->forcedScalarFields;
    }
    if ($this->forcedMultiFields) {
      foreach ($this->forcedMultiFields as $fieldName => $_) {
        unset($fields[$fieldName]);
      }
    }
    return $fields;
  }

  /**
   * Builds the internal cache of scalar fields grouped by ES index name.
   */
  protected function warmScalarFieldMap(): void {
    if ($this->scalarFieldMap !== NULL) {
      return;
    }

    $this->scalarFieldMap = [];
    $storage = $this->entityTypeManager->getStorage('search_api_index');
    foreach ($storage->loadMultiple() as $index) {
      try {
        $server = $index->getServerInstance();
      }
      catch (SearchApiException $exception) {
        continue;
      }
      if (!$server || $server->getBackendId() !== 'elasticsearch') {
        continue;
      }
      $config = $server->getBackendConfig();
      $prefix = $config['advanced']['prefix'] ?? '';
      $suffix = $config['advanced']['suffix'] ?? '';
      $indexName = $prefix . $index->id() . $suffix;
      $fields = [];
      foreach ($index->getFields() as $field) {
        if ($this->isScalarType($field->getType())) {
          $fields[$field->getFieldIdentifier()] = TRUE;
        }
      }
      if ($fields) {
        $this->scalarFieldMap[$indexName] = $fields;
      }
    }
  }

  /**
   * Checks whether a Search API field type is marked as scalar.
   */
  protected function isScalarType(?string $fieldType): bool {
    if (!$fieldType) {
      return FALSE;
    }
    return isset($this->scalarTypeWhitelist[$fieldType]);
  }

  /**
   * Converts a single-value array into a scalar.
   */
  protected function normalizeValue(mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }

    if (!array_is_list($value)) {
      return $value;
    }

    $value = array_values($value);
    $first = $value[0] ?? NULL;
    if (is_array($first) && array_key_exists('value', $first)) {
      return $first['value'];
    }
    return $first;
  }

}
