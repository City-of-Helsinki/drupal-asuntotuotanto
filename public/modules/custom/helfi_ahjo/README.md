# Drupal Ahjo integration

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-ahjo/workflows/CI/badge.svg)

Integrates [OpenAhjo](https://dev.hel.fi/apis/openahjo#documentation) with Drupal.

## Requirements

- PHP 7.4 or higher

## Usage

Available migrations:

- `ahjo_issues`

### Running migrations

Running all Ahjo migrations:
`drush migrate:import --group ahjo`

Running a single migration:

`drush migrate:import {migration_id}` Add `--update` parameter to update existing items.

Reverting a migration:

`drush migrate:rollback {migration_id}`.

Migration failed and the migration process is stuck at importing:

`drush migrate:reset-status {migration_id}`.

### Speed up migrations

Set `PARTIAL_MIGRATE=1` env variable to only migrate changed items. *NOTE:* running a partial migrate will skip
all garbage collection tasks (such as cleaning removed remote entities), so you should periodically run full migrations as well.

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)

Mail: helfi-drupal-aaaactuootjhcono73gc34rj2u@druid.slack.com
