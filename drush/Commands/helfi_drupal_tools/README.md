# Drupal tools

## Installation

- `composer require drupal/helfi_drupal_tools`

Add the following to your composer.json's `installer-paths`:

```
"drush/Commands/{$name}": [
  "type:drupal-drush"
]
```

## Platform update

Update files from `drupal-platform` repository:

- `drush helfi:tools:update-platform`

## Database sync

Add `OC_PROJECT_NAME=hki-kanslia-{your-project-name}` to your `.env` file and run:

- `drush helfi:oc:get-dump`
