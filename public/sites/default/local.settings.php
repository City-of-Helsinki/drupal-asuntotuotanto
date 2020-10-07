<?php
/**
 * @file
 * Drupal 9 development environment configuration file.
 *
 * This file will only be included on development environments.
 */

$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

// Use local Elastic search.
$config['elasticsearch_connector.cluster.local_elasticsearch']['url'] = 'http://asuntotuotanto-elastic';
