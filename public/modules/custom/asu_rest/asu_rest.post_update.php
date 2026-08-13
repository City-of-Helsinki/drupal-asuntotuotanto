<?php

/**
 * @file
 * Post update hooks for asu_rest.
 */

use Drupal\Core\Cache\Cache;

/**
 * Invalidate stale search payload caches after deploying cache hook changes.
 */
function asu_rest_post_update_invalidate_search_payload_cache(&$sandbox = NULL): string {
  Cache::invalidateTags(['apartment_entity_list']);
  return 'Invalidated asu_rest search payload cache tag apartment_entity_list.';
}
