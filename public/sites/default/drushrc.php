<?php

$drush_ignore_modules = [
  'search_api',
  'elasticsearch_connector'
];

$command_specific['config-export']['skip-modules'] = $drush_ignore_modules;
$command_specific['config-import']['skip-modules'] = $drush_ignore_modules;
