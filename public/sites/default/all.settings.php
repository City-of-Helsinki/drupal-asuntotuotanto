<?php

/**
 * @file
 * Contains site specific overrides.
 */

$settings['file_private_path'] = getenv('DRUPAL_FILES_PRIVATE') ?: 'sites/default/files/private';

if ($env = getenv('APP_ENV')) {
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

  $config['elasticsearch_connector.cluster.asuntotuotanto']['url'] = getenv('ASU_ELASTICSEARCH_ADDRESS') ?? 'http://localhost:9200';

  if ($env === 'dev') {
    $orbstack = str_contains(php_uname('r'), 'orbstack');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['url'] = $orbstack ? 'http://elastic.asuntotuotanto.orb.local' : 'http://elastic:9200';

    if ($orbstack) {
      // Mailer settings.
      $config['symfony_mailer_lite.symfony_mailer_lite_transport.smtp']['configuration']['host'] = 'host.docker.internal';
      $config['symfony_mailer_lite.symfony_mailer_lite_transport.smtp']['configuration']['port'] = '1025';
    }
  }

  if ($env === 'test') {
    $config['elasticsearch_connector.cluster.asuntotuotanto']['url'] = 'http://elastic:9200';
  }

  // Development environment.
  if ($env === 'development') {
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['use_authentication'] = 1;
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['authentication_type'] = 'Basic';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['username'] = getenv('ASU_ELASTICSEARCH_USERNAME');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['password'] = getenv('ASU_ELASTICSEARCH_PASSWORD');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['rewrite']['rewrite_index'] = 1;
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['rewrite']['index']['prefix'] = 'asuntotuotanto';


    $config['elasticsearch_connector.index.apartments']['index_id'] = 'asuntotuotanto_apartment';
    $config['elasticsearch_connector.index.apartments']['server'] = 'asuntotuotanto';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['status'] = '1';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['cluster_id'] = 'asuntotuotanto';

    $config['search_api.server.asuntotuotanto']['backend_config']['scheme'] = 'https';
    $config['search_api.server.asuntotuotanto']['backend_config']['host'] = getenv('ASU_ELASTICSEARCH_ADDRESS') ? str_replace(['https://', ':443'], '', getenv('ASU_ELASTICSEARCH_ADDRESS')) : '';
    $config['search_api.server.asuntotuotanto']['backend_config']['port'] = '443';

    $config['raven.settings']['environment'] = 'development';
  }

  // Testing environment.
  if ($env === 'testing') {
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['use_authentication'] = 1;
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['authentication_type'] = 'Basic';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['username'] = getenv('ASU_ELASTICSEARCH_USERNAME');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['password'] = getenv('ASU_ELASTICSEARCH_PASSWORD');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['rewrite']['rewrite_index'] = 1;
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['rewrite']['index']['prefix'] = 'asuntotuotanto';


    $config['elasticsearch_connector.index.apartments']['index_id'] = 'asuntotuotanto_apartment';
    $config['elasticsearch_connector.index.apartments']['server'] = 'asuntotuotanto';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['status'] = '1';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['cluster_id'] = 'asuntotuotanto';

    $config['search_api.server.asuntotuotanto']['backend_config']['scheme'] = 'https';
    $config['search_api.server.asuntotuotanto']['backend_config']['host'] = getenv('ASU_ELASTICSEARCH_ADDRESS') ? str_replace(['https://', ':443'], '', getenv('ASU_ELASTICSEARCH_ADDRESS')) : '';
    $config['search_api.server.asuntotuotanto']['backend_config']['port'] = '443';

    $config['raven.settings']['environment'] = 'testing';
  }

  // Staging environment.
  if ($env === 'stg') {
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['use_authentication'] = 1;
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['authentication_type'] = 'Basic';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['username'] = getenv('ASU_ELASTICSEARCH_USERNAME');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['password'] = getenv('ASU_ELASTICSEARCH_PASSWORD');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['rewrite']['rewrite_index'] = 1;
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['rewrite']['index']['prefix'] = 'asuntotuotanto';


    $config['elasticsearch_connector.index.apartments']['index_id'] = 'asuntotuotanto_apartment';
    $config['elasticsearch_connector.index.apartments']['server'] = 'asuntotuotanto';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['status'] = '1';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['cluster_id'] = 'asuntotuotanto';

    $config['search_api.server.asuntotuotanto']['backend_config']['scheme'] = 'https';
    $config['search_api.server.asuntotuotanto']['backend_config']['host'] = getenv('ASU_ELASTICSEARCH_ADDRESS') ? str_replace(['https://', ':443'], '', getenv('ASU_ELASTICSEARCH_ADDRESS')) : '';
    $config['search_api.server.asuntotuotanto']['backend_config']['port'] = '443';

    $config['raven.settings']['environment'] = 'staging';
  }

  // Production environment.
  if ($env === 'prod') {
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['use_authentication'] = 1;
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['authentication_type'] = 'Basic';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['username'] = getenv('ASU_ELASTICSEARCH_USERNAME');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['password'] = getenv('ASU_ELASTICSEARCH_PASSWORD');
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['rewrite']['rewrite_index'] = 1;
    $config['elasticsearch_connector.cluster.asuntotuotanto']['options']['rewrite']['index']['prefix'] = 'asuntotuotanto';


    $config['elasticsearch_connector.index.apartments']['index_id'] = 'asuntotuotanto_apartment';
    $config['elasticsearch_connector.index.apartments']['server'] = 'asuntotuotanto';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['status'] = '1';
    $config['elasticsearch_connector.cluster.asuntotuotanto']['cluster_id'] = 'asuntotuotanto';

    $config['search_api.server.asuntotuotanto']['backend_config']['scheme'] = 'https';
    $config['search_api.server.asuntotuotanto']['backend_config']['host'] = getenv('ASU_ELASTICSEARCH_ADDRESS') ? str_replace(['https://', ':443'], '', getenv('ASU_ELASTICSEARCH_ADDRESS')) : '';
    $config['search_api.server.asuntotuotanto']['backend_config']['port'] = '443';

    $config['raven.settings']['environment'] = 'production';
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
}
