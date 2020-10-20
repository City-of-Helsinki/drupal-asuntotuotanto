<?php

namespace Drupal\asu_csv_import;

use Drupal\asu_csv_import\ImportTypes\DateType;
use Drupal\asu_csv_import\ImportTypes\BooleanType;
use Drupal\asu_csv_import\ImportTypes\DecimalType;
use Drupal\asu_csv_import\ImportTypes\LinkType;
use Drupal\asu_csv_import\ImportTypes\TextType;
use Drupal\asu_csv_import\ImportTypes\NumberType;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

/**
 * Class to handle csv upload logic.
 */
class UploadFileHandler {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

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
   * Constructor.
   */
  public function __construct() {
    $this->entityFieldManager = \Drupal::service('entity_field.manager');
    $this->languageManager = \Drupal::languageManager();
    $this->translationManager = \Drupal::translation();
  }

  /**
   * Validate data entered to csv file.
   *
   * @param Drupal\file\Entity\File $file
   *   Csv file.
   *
   * @return array
   *   Array of error messages.
   */
  public function validateImportData(File $file) {
    $errors = [];
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', 'apartment');

    if (($handle = fopen($file->getFileUri(), "r")) !== FALSE) {
      $i = 0;
      $header = [];

      // Each row represents old or new apartment node.
      while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== FALSE) {
        if ($i == 0) {
          $header = $row;
          $field_types = $this->getFieldTypes($row, $field_definitions);
          $i++;

          continue;
        }

        // Loop through the fields. Create function throws exception if data is invalid.
        foreach ($row as $key => $data) {
          $type = $field_types[$key];
          try {
            $this->createValue($data, $type);
          }
          catch (\Exception $e) {
            $line = $i + 1;
            $column_number = $key + 1;
            $column_name = $header[$key];
            $error = "Invalid value on line $line, on column ($column_number) $column_name";
            $errors[] = $error;
            continue;
          }
        }

        $i++;
      }
      fclose($handle);
    }

    return $errors;
  }

  /**
   * Create array of nodes to create or update.
   *
   * @param Drupal\file\Entity\File $file
   *   Csv file.
   *
   * @return array
   *   Array of nodes to update or create.
   */
  public function createNodes(File $file) {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', 'apartment');
    $update_nodes = [];
    $create_nodes = [];

    if (($handle = fopen($file->getFileUri(), "r")) !== FALSE) {
      $i = 0;

      // Each row represents old or new apartment node.
      while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== FALSE) {
        // Get header for fields machine names.
        if ($i == 0) {
          $header = $row;
          $field_types = $this->getFieldTypes($header, $field_definitions);
          $i++;
          continue;
        }

        // These are constant.
        $node_fields = [
          'type' => 'apartment',
          'title' => '',
        ];

        // Create data array which is used to update or create node.
        foreach ($row as $key => $data) {
          if (!$data) {
            continue;
          }
          $type = $field_types[$key];

          /** @var \Drupal\asu_csv_import\ImportTypes\ImportType $data */
          $data = $this->createValue($data, $type);

          $node_fields[$header[$key]] = $data->getImportValue();
        }

        // Update existing or create new node.
        if (isset($node_fields['nid'])) {
          $node = Node::load($node_fields['nid']);
          foreach ($node_fields as $key => $val) {
            $value = $node->{$key}->value ? $node->{$key}->value : $node->{$key}->getString();
            if ($key == 'field_showing_time') {
              $d = new \DateTime($value);
              $node->field_showing_time->setValue($d->format('Y-m-d\TH:i:s'));
            }
            if (!$value || $value === $val) {
              continue;
            }
            $node->{$key} = $val;

          }
          $update_nodes[] = $node;
        }
        else {
          $create_nodes[] = Node::create($node_fields);
        }

        $i++;
      }
      fclose($handle);
    }

    return [
      'update' => $update_nodes,
      'create' => $create_nodes,
    ];
  }

  /**
   * Get uploaded csv file from form.
   *
   * @param array $fields
   *   Content type fields.
   *
   * @return bool|Drupal\Core\Entity\EntityInterface|File|null
   *   Uploaded file.
   */
  public function getUploadedFile(array $fields) {
    foreach ($fields as $field) {
      $type = $field->getFieldDefinition()->getType();
      if ($type == 'asu_csv_import') {
        $file_id = $field->getValue()[0]['target_id'];
        $file = File::load($file_id);
        return $file;
      }
    }
    return FALSE;
  }

  /**
   * Get field definitions by field machine names in csv header.
   *
   * @param array $header
   *   Array of machine names.
   * @param array $field_definitions
   *   Content type field definitions.
   *
   * @return array
   *   Array of field types by machine name.
   */
  private function getFieldTypes(array $header, array $field_definitions) {
    $field_types = [];
    foreach ($header as $key => $title) {
      if (isset($field_definitions[$title])) {
        $field_types[] = $field_definitions[$title]->getType();
      }
    }
    return $field_types;
  }

  /**
   * Create and validate different data types.
   *
   * @param mixed $data
   *   Data which will be turned into value object.
   * @param string $type
   *   Field type.
   *
   * @return ImportTypes\ImportType
   *   Object containing value.
   *
   * @throws \Exception
   */
  public function createValue($data, $type) {
    switch ($type) {
      case 'integer':
        return new NumberType($data);

      break;
      case 'string':
      case 'string_long':
        return new TextType($data);

      break;
      case 'link':
        return new LinkType($data);

      break;
      case 'decimal':
        return new DecimalType($data);

      break;
      case 'boolean':
        return new BooleanType($data);

      break;
      case 'datetime':
        return new DateType($data);

      break;
      default:
        return FALSE;
      /*
      case 'list_integer':
      // Lists should not be used, use taxonomies instead.
      break;
      case 'asu_computed_render_array':
      // Computed fields should be skipped.
      break;
       */
    }
  }

}
