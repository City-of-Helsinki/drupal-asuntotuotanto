<?php

namespace Drupal\asu_elastic\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\QueryParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Force district fields to use keyword mapping instead of text.
 *
 * This ensures that district names like "Pohjois-Pasila" are indexed as
 * complete keywords and not tokenized into separate words.
 */
class DistrictFieldMappingSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FieldMappingEvent::class => 'onFieldMapping',
      QueryParamsEvent::class => 'onQueryParams',
    ];
  }

  /**
   * Modifies field mapping for district fields.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   The field mapping event.
   */
  public function onFieldMapping(FieldMappingEvent $event): void {
    $field = $event->getField();
    $fieldId = $field->getFieldIdentifier();

    // Force keyword mapping for district field to prevent tokenization.
    if ($fieldId === 'project_district') {
      $event->setParam(['type' => 'keyword']);
    }
  }

  /**
   * Modifies Elasticsearch query use .keyword sub-field for project_district.
   *
   * Also expands base district names to include sub-districts.
   * For example: "Pasila" will match both "Pasila" and "Pohjois-Pasila".
   *
   * @param \Drupal\elasticsearch_connector\Event\QueryParamsEvent $event
   *   The query params event.
   */
  public function onQueryParams(QueryParamsEvent $event): void {
    $params = $event->getParams();

    // Only process if we have a body with a query.
    if (!isset($params['body']['query'])) {
      return;
    }

    // Recursively replace project_district with enhanced matching logic.
    $params['body']['query'] = $this->replaceDistrictField(
      $params['body']['query']
    );

    $event->setParams($params);
  }

  /**
   * Recursively replaces project_district with enhanced matching.
   *
   * @param mixed $query
   *   The query array or value.
   *
   * @return mixed
   *   The modified query.
   */
  protected function replaceDistrictField($query) {
    if (!is_array($query)) {
      return $query;
    }

    $result = [];
    foreach ($query as $key => $value) {
      // Check if this is a terms/term query with project_district.
      if (
        ($key === 'terms' || $key === 'term')
        && is_array($value)
        && isset($value['project_district'])
      ) {
        // Replace this entire element with enhanced district query.
        return $this->createDistrictQuery(
          $value['project_district'],
          $key === 'terms'
        );
      }
      else {
        // Recursively process nested arrays.
        $result[$key] = is_array($value)
          ? $this->replaceDistrictField($value)
          : $value;
      }
    }

    return $result;
  }

  /**
   * Creates enhanced district query supporting base + sub-districts.
   *
   * @param mixed $districts
   *   The district value(s) to search for.
   * @param bool $isTerms
   *   Whether this is a terms query (array) or term query (single value).
   *
   * @return array
   *   The enhanced query structure.
   */
  protected function createDistrictQuery(
    $districts,
    bool $isTerms = TRUE,
  ): array {
    $shouldClauses = [];

    // Normalize to array.
    if (!is_array($districts)) {
      $districts = [$districts];
    }

    foreach ($districts as $district) {
      if (strpos($district, '-') === FALSE) {
        // Base district (no hyphen): match exact OR sub-districts.
        $shouldClauses[] = [
          'term' => ['project_district' => $district],
        ];
        $shouldClauses[] = [
          'wildcard' => ['project_district' => '*-' . $district],
        ];
      }
      else {
        // Sub-district (has hyphen): exact match only.
        $shouldClauses[] = [
          'term' => ['project_district' => $district],
        ];
      }
    }

    // Return bool query with should clauses.
    return [
      'bool' => [
        'should' => $shouldClauses,
        'minimum_should_match' => 1,
      ],
    ];
  }

}
