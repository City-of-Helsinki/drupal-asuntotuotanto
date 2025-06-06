<?php

use Symfony\Component\HttpFoundation\Request;

if (PHP_SAPI === 'cli') {
  ini_set('memory_limit', '512M');
}
else {
  // New relic triggers garbage collector which adds extra time on the request.
  // The gc enabled is useful for migration drush commands and probably others.
  // For non cli requests, there should not be a case where gc is called / needed.
  ini_set('zend.enable_gc', 'Off');
}


if (!function_exists('drupal_get_env')) {
  /**
   * Gets the value of given environment variable.
   *
   * @param string|array $variables
   *   The variables to scan.
   *
   * @return mixed
   *   The value.
   */
  function drupal_get_env(string|array $variables) : mixed {
    if (!is_array($variables)) {
      $variables = [$variables];
    }

    foreach ($variables as $var) {
      if ($value = getenv($var)) {
        return $value;
      }
    }
    return NULL;
  }
}

if ($simpletest_db = getenv('SIMPLETEST_DB')) {
  $parts = parse_url($simpletest_db);
  putenv(sprintf('DRUPAL_DB_NAME=%s', substr($parts['path'], 1)));
  putenv(sprintf('DRUPAL_DB_USER=%s', $parts['user']));
  putenv(sprintf('DRUPAL_DB_PASS=%s', $parts['pass']));
  putenv(sprintf('DRUPAL_DB_HOST=%s', $parts['host']));
}

$databases['default']['default'] = [
  'database' => getenv('DRUPAL_DB_NAME'),
  'username' => getenv('DRUPAL_DB_USER'),
  'password' => getenv('DRUPAL_DB_PASS'),
  'prefix' => '',
  'host' => getenv('DRUPAL_DB_HOST'),
  'port' => getenv('DRUPAL_DB_PORT') ?: 3306,
  'namespace' => 'Drupal\Core\Database\Driver\mysql',
  'driver' => 'mysql',
  'charset' => 'utf8mb4',
  'collation' => 'utf8mb4_swedish_ci',
  'init_commands' => [
    'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
  ],
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: '000';

// Only in Wodby environment.
// @see https://wodby.com/docs/stacks/drupal/#overriding-settings-from-wodbysettingsphp
if (isset($_SERVER['WODBY_APP_NAME'])) {
  // The include won't be added automatically if it's already there.
  // phpcs:ignore
  include_once '/var/www/conf/wodby.settings.php'; // NOSONAR
}

$config['scheduler.settings']['lightweight_cron_access_key'] = getenv('DRUPAL_SCHEDULER_CRON_KEY') ?: $settings['hash_salt'];

$config['openid_connect.client.tunnistamo']['settings']['client_id'] = getenv('TUNNISTAMO_CLIENT_ID');
$config['openid_connect.client.tunnistamo']['settings']['client_secret'] = getenv('TUNNISTAMO_CLIENT_SECRET');

if ($tunnistamo_environment_url = getenv('TUNNISTAMO_ENVIRONMENT_URL')) {
  $config['openid_connect.client.tunnistamo']['settings']['environment_url'] = $tunnistamo_environment_url;
}

$config['siteimprove.settings']['prepublish_enabled'] = TRUE;
$config['siteimprove.settings']['api_username'] = getenv('SITEIMPROVE_API_USERNAME');
$config['siteimprove.settings']['api_key'] = getenv('SITEIMPROVE_API_KEY');

$settings['matomo_site_id'] = getenv('MATOMO_SITE_ID');
$settings['siteimprove_id'] = getenv('SITEIMPROVE_ID');

$routes = [];
// Drupal route(s).
if ($drupal_routes = getenv('DRUPAL_ROUTES')) {
  $routes = array_map(fn (string $route) => trim($route), explode(',', $drupal_routes));
}
$routes[] = 'http://127.0.0.1';

if ($simpletest_base_url = getenv('SIMPLETEST_BASE_URL')) {
  $routes[] = $simpletest_base_url;
}

if ($drush_options_uri = getenv('DRUSH_OPTIONS_URI')) {
  $routes[] = $drush_options_uri;
}

foreach ($routes as $route) {
  $host = parse_url($route, PHP_URL_HOST);
  $trusted_host = str_replace('.', '\.', $host);
  $settings['trusted_host_patterns'][] = '^' . $trusted_host . '$';
}

$settings['config_sync_directory'] = '../conf/cmi';
$settings['file_public_path'] = getenv('DRUPAL_FILES_PUBLIC') ?: 'sites/default/files';
$settings['file_private_path'] = getenv('DRUPAL_FILES_PRIVATE') ?: 'sites/default/files/private';
$settings['file_temp_path'] = getenv('DRUPAL_TMP_PATH') ?: '/tmp';

if ($reverse_proxy_address = getenv('DRUPAL_REVERSE_PROXY_ADDRESS')) {
  $reverse_proxy_address = explode(',', $reverse_proxy_address);

  if (isset($_SERVER['REMOTE_ADDR'])) {
    $reverse_proxy_address[] = $_SERVER['REMOTE_ADDR'];
  }
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = $reverse_proxy_address;
  $settings['reverse_proxy_trusted_headers'] = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;
  $settings['reverse_proxy_host_header'] = 'X_FORWARDED_HOST';
}

if ($blob_storage_name = getenv('AZURE_BLOB_STORAGE_NAME')) {
  $schemes = [
    'azure' => [
      'driver' => 'helfi_azure',
      'config' => [
        'name' => $blob_storage_name,
        'key' => drupal_get_env([
          'AZURE_BLOB_STORAGE_KEY',
          'BLOBSTORAGE_ACCOUNT_KEY',
        ]),
        'token' => drupal_get_env([
          'AZURE_BLOB_STORAGE_SAS_TOKEN',
          'BLOBSTORAGE_SAS_TOKEN',
        ]),
        'container' => getenv('AZURE_BLOB_STORAGE_CONTAINER'),
        'endpointSuffix' => 'core.windows.net',
        'protocol' => 'https',
      ],
      'cache' => TRUE,
    ],
  ];
  $config['helfi_azure_fs.settings']['use_blob_storage'] = TRUE;
  $settings['flysystem'] = $schemes;
}


if ($navigation_authentication_key = getenv('DRUPAL_NAVIGATION_API_KEY')) {
  $config['helfi_navigation.api']['key'] = $navigation_authentication_key;
}

// Make sure project name and app env are defined in GitHub actions too.
if ($github_repository = getenv('GITHUB_REPOSITORY')) {
  if (!getenv('APP_ENV')) {
    putenv('APP_ENV=ci');
  }

  if (!getenv('PROJECT_NAME')) {
    putenv('PROJECT_NAME=' . $github_repository);
  }
}
$config['helfi_api_base.environment_resolver.settings']['environment_name'] = getenv('APP_ENV');
$config['helfi_api_base.environment_resolver.settings']['project_name'] = getenv('PROJECT_NAME');

if ($varnish_host = getenv('DRUPAL_VARNISH_HOST')) {
  // Cache everything for 1 year by default.
  $config['system.performance']['cache']['page']['max_age'] = 31536000;

  $varnish_backend = parse_url($drush_options_uri, PHP_URL_HOST);

  if (getenv('APP_ENV') === 'local') {
    // Varnish backend is something like varnish-helfi-kymp.docker.so on
    // local env.
    $varnish_backend = 'varnish-' . $varnish_backend;
  }

  // settings.php doesn't know about existing configuration yet so we can't
  // just append new headers to an already existing headers array here.
  // If you have configured any extra headers in your purge settings
  // you must add them in your all.settings.php as well.
  // @todo Replace this with config override service?
  $varnishConfiguration = [
    'default' => [
      [
        'field' => 'Cache-Tags',
        'value' => '[invalidation:expression]',
      ],
    ],
    'assets' => [
      [
        'field' => 'X-VC-Purge-Method',
        'value' => 'regex',
      ],
      [
        'field' => 'Host',
        'value' => $varnish_backend,
      ],
    ],
    'varnish_purge_all' => [
      [
        'field' => 'X-VC-Purge-Method',
        'value' => 'regex',
      ],
    ],
  ];

  foreach ($varnishConfiguration as $name => $headers) {
    $config['varnish_purger.settings.' . $name]['hostname'] = $varnish_host;

    if ($varnish_port = getenv('DRUPAL_VARNISH_PORT')) {
      $config['varnish_purger.settings.' . $name]['port'] = $varnish_port;
    }

    foreach ($headers as $header) {
      $config['varnish_purger.settings.' . $name]['headers'][] = $header;
    }

    if ($varnish_purge_key = getenv('VARNISH_PURGE_KEY')) {
      $config['varnish_purger.settings.' . $name]['headers'][] = [
        'field' => 'X-VC-Purge-Key',
        'value' => $varnish_purge_key,
      ];
    }
  }
}
$stage_file_proxy_origin = getenv('STAGE_FILE_PROXY_ORIGIN');
$stage_file_proxy_dir = getenv('STAGE_FILE_PROXY_ORIGIN_DIR');

if ($stage_file_proxy_origin || $stage_file_proxy_dir) {
  $config['stage_file_proxy.settings']['origin'] = $stage_file_proxy_origin ?: 'https://stplattaprod.blob.core.windows.net';
  $config['stage_file_proxy.settings']['origin_dir'] = $stage_file_proxy_dir;
  $config['stage_file_proxy.settings']['hotlink'] = FALSE;
  $config['stage_file_proxy.settings']['use_imagecache_root'] = FALSE;
}

if ($drupal_pubsub_vault = getenv('DRUPAL_PUBSUB_VAULT')) {
  $config['helfi_api_base.api_accounts']['vault'][] = [
    'id' => 'pubsub',
    'plugin' => 'json',
    'data' => trim($drupal_pubsub_vault),
  ];
}

if ($drupal_navigation_vault = getenv('DRUPAL_NAVIGATION_VAULT')) {
  $config['helfi_api_base.api_accounts']['vault'][] = [
    'id' => 'helfi_navigation',
    'plugin' => 'authorization_token',
    'data' => trim($drupal_navigation_vault),
  ];
}

// Override session suffix when present.
if ($session_suffix = getenv('DRUPAL_SESSION_SUFFIX')) {
  $config['helfi_proxy.settings']['session_suffix'] = $session_suffix;
}

if ($robots_header_enabled = getenv('DRUPAL_X_ROBOTS_TAG_HEADER')) {
  $config['helfi_proxy.settings']['robots_header_enabled'] = (bool) $robots_header_enabled;
}

$artemis_destination = drupal_get_env([
  'ARTEMIS_DESTINATION',
  'PROJECT_NAME',
]);

$artemis_brokers = getenv('ARTEMIS_BROKERS');

if ($artemis_brokers && $artemis_destination) {
  $settings['stomp']['default'] = [
    'clientId' => getenv('ARTEMIS_CLIENT_ID') ?: 'artemis',
    'login' => getenv('ARTEMIS_LOGIN') ?: NULL,
    'passcode' => getenv('ARTEMIS_PASSCODE') ?: NULL,
    'destination' => sprintf('/queue/%s', $artemis_destination),
    'brokers' => $artemis_brokers,
    'timeout' => ['read' => 12000],
    'heartbeat' => [
      'send' => 20000,
      'receive' => 0,
      'observers' => [
        [
          'class' => '\Stomp\Network\Observer\HeartbeatEmitter',
        ],
      ],
    ],
  ];
  $settings['queue_default'] = 'queue.stomp.default';
}

$config['filelog.settings']['rotation']['schedule'] = 'never';

if (
  ($redis_host = getenv('REDIS_HOST')) &&
  file_exists('modules/contrib/redis/redis.services.yml') &&
  extension_loaded('redis')
) {
  // Redis namespace is not available until redis module is enabled, so
  // we have to manually register it in order to enable the module and have
  // this configuration when the module is installed, but not yet enabled.
  $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');
  $redis_port = getenv('REDIS_PORT') ?: 6379;

  if ($redis_prefix = getenv('REDIS_PREFIX')) {
    $settings['cache_prefix']['default'] = $redis_prefix;
  }

  if ($redis_password = getenv('REDIS_PASSWORD')) {
    $settings['redis.connection']['password'] = $redis_password;
  }
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['port'] = $redis_port;

  // REDIS_INSTANCE environment variable is used to support Redis sentinel.
  // REDIS_HOST value should contain host and port, like 'sentinel:5000'
  // when using Sentinel.
  if ($redis_instance = getenv('REDIS_INSTANCE')) {
    $settings['redis.connection']['instance'] = $redis_instance;
    // Sentinel expects redis host to be an array.
    $redis_host = explode(',', $redis_host);
  }
  $settings['redis.connection']['host'] = $redis_host;

  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';
  // Register redis services to make sure we don't get a non-existent service
  // error while trying to enable the module.
  $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';
}

$settings['is_azure'] = FALSE;

if ($tfa_key = getenv('TFA_ENCRYPTION_KEY')) {
  $config['key.key.tfa']['key_provider_settings']['key_value'] = $tfa_key;
  $config['key.key.tfa']['key_provider_settings']['base64_encoded'] = TRUE;
}

/**
 * Deployment preflight checks.
 *
 * @see docker/openshift/preflight/preflight.php for more information.
 */
$preflight_checks = [
  'environmentVariables' => [
    'DRUPAL_ROUTES',
    'DRUPAL_DB_NAME',
    'DRUPAL_DB_PASS',
    'DRUPAL_DB_HOST',
  ],
  'additionalFiles' => [],
];

// Environment specific overrides.
if (file_exists(__DIR__ . '/all.settings.php')) {
  // phpcs:ignore
  include_once __DIR__ . '/all.settings.php'; // NOSONAR
}

if ($env = getenv('APP_ENV')) {
  if (file_exists(__DIR__ . '/' . $env . '.settings.php')) {
    // phpcs:ignore
    include_once __DIR__ . '/' . $env . '.settings.php'; // NOSONAR
  }

  $servicesFiles = [
    'services.yml',
    'all.services.yml',
    $env . '.services.yml',
  ];

  foreach ($servicesFiles as $fileName) {
    if (file_exists(__DIR__ . '/' . $fileName)) {
      $settings['container_yamls'][] = __DIR__ . '/' . $fileName;
    }
  }

  if (getenv('OPENSHIFT_BUILD_NAMESPACE') && file_exists(__DIR__ . '/azure.settings.php')) {
    // phpcs:ignore
    include_once __DIR__ . '/azure.settings.php'; // NOSONAR
  }
}

if ($env = getenv('APP_ENV')) {
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
        $config['search_api.server.asuntotuotanto']['backend_config']['connector'] = 'helfi_connector';
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
}

/**
 * Deployment identifier.
 *
 * Default 'deployment_identifier' cache key to modified time of 'composer.lock'
 * file in case it's not already defined.
 */
if (empty($settings['deployment_identifier'])) {
  $settings['deployment_identifier'] = filemtime(__DIR__ . '/../../../composer.lock');
}
