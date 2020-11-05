# Asu translation

A module which creates translation file for elasticsearch index keys and saves them to {langcode}.po file.

#### TranslationFileWriter -service

Writes translation files in public folder.

### How it works

Translation file generation is hooked to SearchApi's index form's submit handler.
