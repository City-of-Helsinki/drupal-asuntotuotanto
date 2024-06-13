<?php

namespace Drupal\asu_translation;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\field\Entity\FieldConfig;

/**
 * Writes translation file.
 */
class TranslationFileWriter {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translationManager;

  /**
   * The File Repository Interface.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManager $entityTypeManager, LanguageManager $languageManager, TranslationManager $translationManager, FileRepositoryInterface $fileRepository) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->translationManager = $translationManager;
    $this->fileRepository = $fileRepository;
  }

  /**
   * Get translations for the fields.
   *
   * @param array $fields
   *   Fields to add to the translation files.
   */
  protected function getFieldTranslations(array $fields) {
    $original_language = $this->languageManager->getCurrentLanguage();
    $translations = [];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $this->languageManager->setConfigOverrideLanguage($language);

      foreach ($fields as $field) {
        $type = $field->getDataDefinition()->getFieldDefinition()->getTargetEntityTypeId();
        $bundle = $field->getDataDefinition()->getFieldDefinition()->getTargetBundle();
        $name = $field->getDataDefinition()->getFieldDefinition()->getName();

        $field_config = FieldConfig::loadByName($type, $bundle, $name);

        if ($field_config) {
          $translations[$langcode][$field->getFieldIdentifier()] = $field_config->getLabel();
        }
        else {
          $config_name = "field.field.node.$bundle.$name";
          $config_translation = $this->languageManager->getLanguageConfigOverride($langcode, $config_name);

          if ($config_translation && !$config_translation->isNew()) {
            $translations[$langcode][$field->getFieldIdentifier()] = $config_translation->getLabel();
          }
          else {
            // Computed field translations are in UI translations.
            if ($translation = $this->translationManager->getStringTranslation($langcode, strtolower($field->getLabel()), 'node_fields')) {
              $translations[$langcode][$field->getFieldIdentifier()] = $translation;
            }
          }

        }
      }
    }

    $this->languageManager->setConfigOverrideLanguage($original_language);
    return $translations;
  }

  /**
   * Write the po files.
   *
   * @param array $translations
   *   Translation array.
   */
  protected function doWriteTranslationFiles(array $translations) {
    foreach ($translations as $langcode => $translation_list) {
      $fh = fopen("public://$langcode.po", 'w+');

      fwrite($fh, "#\n");
      fwrite($fh, "msgid \"\"\n");
      fwrite($fh, "msgstr \"\"\n");

      foreach ($translation_list as $msgid => $msgstr) {
        $key = addslashes($msgid);
        $value = addslashes($msgstr);
        fwrite($fh, "\n");
        fwrite($fh, "msgid \"$key\"\n");
        fwrite($fh, "msgstr \"$value\"\n");
      }
      $this->fileRepository->writeData($fh, 'public://<filename>');
      fclose($fh);
    }
  }

}
