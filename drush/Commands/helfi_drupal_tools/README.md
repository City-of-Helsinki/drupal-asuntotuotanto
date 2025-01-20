# Drupal tools

![CI](https://github.com/City-of-Helsinki/drupal-tools/workflows/CI/badge.svg) [![codecov](https://codecov.io/gh/City-of-Helsinki/drupal-tools/graph/badge.svg?token=DPQQ7001DP)](https://codecov.io/gh/City-of-Helsinki/drupal-tools)

Provides Drush commands to perform various tasks.

## Installation

- `composer require drupal/helfi_drupal_tools`

Add the following to your composer.json's `installer-paths`:

```
"drush/Commands/{$name}": [
  "type:drupal-drush"
]
```

## Platform update

### Usage

- `drush helfi:tools:update-platform`

This will:

- Check if `drush/helfi_drupal_tools` package is up-to-date
- Update/add files from [City-of-Helsinki/drupal-helfi-platform](https://github.com/City-of-Helsinki/drupal-helfi-platform) repository
- Attempts to update external packages
- Run the update hooks

### Self update

You should always update `drupal/helfi_drupal_tools` package before running the command:

- `composer update drupal/helfi_drupal_tools`

This check can be disabled by passing `--no-self-update` flag.

### Auto updated files

Certain files are deemed required and will always be updated. See [::updateDefaultFiles() and ::addDefaultFiles()](/UpdateCommands.php) methods for an up-to-date list of these files.

Files can be ignored by creating a file called `.platform/ignore`. The file should contain one file per line.

For example, add something like this to your `ignore` file to never update `settings.php` and `Dockerfile` files:

```
public/sites/default/settings.php
docker/openshift/Dockerfile
```

This check can be bypassed with `--no-ignore-files` flag, meaning the files will be processed regardless of `ignore` file.

### Update external tools

At the moment, these tools are updated automatically:
- https://github.com/druidfi/tools

This can be disabled by passing `--no-update-external-packages` flag.

### Update hooks

Running update hooks will create a file called `.platform/schema`. The file contains the current schema version and should be committed to Git.

This can be disabled by passing `--no-run-migrations` flag.

## Developing update hook

The hooks should be defined in `src/Update/migrations.php` file and each hook should increment by one, just like `hook_update_N()` in Drupal.

The hook _MUST_ be re-entrant, meaning it must succeed no matter how many times it's run.

An optional `UpdateOptions $options`, `FileManager $fileManager`, and `Filesystem $fileSystem` arguments are passed to update hook.

The hook must return a `UpdateResult` object. You can optionally pass an array of messages that will be collected and printed by the UpdateCommands command.

An exception should be thrown if the update does not succeed.

### Run arbitrary commands

Use Symfony's Process library to run commands:
```php
$process = new Process([
  'command',
  '--argument1=something',
  '--argument2=something_else',
]);
$process->run();

if (!$process->isSuccessful()) {
  throw new \InvalidArgumentException(
    sprintf('Process failed with output: %s', $process->getErrorOutput())
  );
}
```

### Remove files

Calling `$filemanager->removefiles()` multiple times should be safe, even if the file does not exist.

You can either pass an entire folder or individual files:

```php
$fileManager->removeFiles($options, [
  'file1 to remove',
  'file2 to remove',
  'folder/to/remove',
]);
```

### Adding files

This can be used to add new files once. Should contain a filename => settings array pairs.

Available settings:
- `remote`: Determines if the file should be copied from `drupal-helfi-platform` repository
- `content`: The file content. Only used when `remote` is set to false
- `destination`: The destination where the file should be placed. Defaults to source's filename

For example, to copy file from `drupal-helfi-platform` repository:
```php
// This will only be copied if the file does not exist yet.
$fileManager->addFiles($options, [
  'public/sites/default/all.settings.php' => [
    'remote' => TRUE,
  ]
]);
$fileManager->addFiles($options, [
  'public/sites/default/all.settings.php' => [
    'content' => 'file content',
  ],
]);
```

### Update files

Updates the given file from `drupal-helfi-platform` repository.

For example, should contain source => destination key pairs:
```php
// If the key is not set, the source and destination will default to the same value.
// For example, this will copy public/index.php from Platform
// repository and override local public/index.php with the contents.
$fileManager->updateFiles($options, [
  'public/index.php',
]);
// This will copy 'file1' from Platform repository and save it as 'file2'.
$fileManager->updateFiles($options, [
  'file1' => 'file2',
])
```
## Checking composer package versions

Checks that packages are up-to-date. This is used as a part of our [Automatic update workflow](https://github.com/City-of-Helsinki/drupal-helfi-platform/blob/main/documentation/automatic-updates.md) to warn if composer was unable to update any of our dependencies.

Run `drush helfi:tools:check-composer-versions /path/to/composer.lock`.

## Running tests

- `composer install`
- `vendor/bin/phpunit -c tests/phpunit.xml tests/`

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)
