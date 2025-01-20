<?php

/**
 * @file
 * Contains site specific overrides.
 */

// Elastic settings.
$settings['ASU_ELASTICSEARCH_ADDRESS'] = getenv('ASU_ELASTICSEARCH_ADDRESS')  ?? 'http://elastic:9200';
$settings['ASU_ELASTICSEARCH_USERNAME'] = getenv('ASU_ELASTICSEARCH_USERNAME');
$settings['ASU_ELASTICSEARCH_PASSWORD'] = getenv('ASU_ELASTICSEARCH_PASSWORD');

// Email settings.
$config['mailsystem.settings']['defaults']['sender'] = 'symfony_mailer_lite';
$config['mailsystem.settings']['defaults']['formatter'] = 'symfony_mailer_lite';
$config['mailsystem.settings']['modules']['symfony_mailer_lite']['none']['formatter'] = 'symfony_mailer_lite';
$config['mailsystem.settings']['modules']['symfony_mailer_lite']['none']['sender'] = 'symfony_mailer_lite';
$config['symfony_mailer_lite.settings']['default_transport'] = 'smtp';

$settings['ASU_DJANGO_BACKEND_URL'] = getenv('ASU_DJANGO_BACKEND_URL');

// Supported values: https://github.com/Seldaek/monolog/blob/main/doc/01-usage.md#log-levels.
$default_log_level = getenv('APP_ENV') === 'production' ? 'info' : 'debug';
$settings['helfi_api_base.log_level'] = getenv('LOG_LEVEL') ?: $default_log_level;

if ($env === 'dev') {
  $orbstack = str_contains(php_uname('r'), 'orbstack');
  $config['search_api.server.asuntotuotanto']['backend_config']['connector_config']['url'] = $orbstack ? 'http://elastic.asuntotuotanto.orb.local' : 'http://elastic:9200';

  if ($orbstack) {
    // Mailer settings.
    $config['symfony_mailer_lite.symfony_mailer_lite_transport.smtp']['configuration']['host'] = 'host.docker.internal';
    $config['symfony_mailer_lite.symfony_mailer_lite_transport.smtp']['configuration']['port'] = '1025';
  }
}

// Whitelist azure environments.
$whitelist = ['development', 'testing', 'stg', 'prod'];

if (in_array($env, $whitelist)) {
  // Elasticsearch settings.
  if (getenv('ASU_ELASTICSEARCH_URL')) {
    $config['search_api.server.asuntotuotanto']['backend_config']['connector_config']['url'] = getenv('ASU_ELASTICSEARCH_URL');

    if (getenv('ASU_ELASTICSEARCH_USERNAME') && getenv('ASU_ELASTICSEARCH_PASSWORD')) {
      $config['search_api.server.asuntotuotanto']['backend_config']['connector'] = 'basicauth';
      $config['search_api.server.asuntotuotanto']['backend_config']['connector_config']['username'] = getenv('ASU_ELASTICSEARCH_USERNAME');
      $config['search_api.server.asuntotuotanto']['backend_config']['connector_config']['password'] = getenv('ASU_ELASTICSEARCH_PASSWORD');
    }
  }
  $config['raven.settings']['environment'] = $env;
  $config['raven.settings']['public_dsn'] = getenv('SENTRY_DSN') ?? '';
  $config['metatag.metatag_defaults.global']['tags']['robots'] = 'index, follow';
  $config['helfi_api_base.features']['disable_email_sending'] = FALSE;
}

// CI environment.
if ($env === 'ci') {
  $config['search_api.server.asuntotuotanto']['backend_config']['scheme'] = 'https';
  $config['search_api.server.asuntotuotanto']['backend_config']['host'] = 'localhost';
  $config['search_api.server.asuntotuotanto']['backend_config']['port'] = '443';
}

$config['helfi_api_base.features']['user_expire'] = FALSE;

// Saml Authentication.
include 'saml.settings.php';
