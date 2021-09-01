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

    // @todo Throw exception, delimiter not found.
    $delimiter = $this->getFileDelimiter($file->getFileUri(), "r");

    if (($handle = fopen($file->getFileUri(), "r")) !== FALSE) {
      $i = 0;
      $header = [];

      // Each row represents old or new apartment node.
      while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== FALSE) {
        if ($i == 0) {
          $header = $row;
          $field_types = $this->getFieldTypes($row, $field_definitions);
          $i++;

          continue;
        }

        foreach ($row as $key => $data) {
          $type = $field_types[$key];
          try {
            $this->createValue(trim($data), $type);
          }
          catch (\Exception $e) {
            $line = $i + 1;
            $column_number = $key + 1;
            $column_name = $header[$key];
            $error = "Invalid value on line $line, on column ($column_number) $column_name: {$e->getMessage()}";
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
   * @param string $langcode
   *   Language code from the form state.
   *
   * @return array
   *   Array of nodes to update or create.
   */
  public function createNodes(File $file, $langcode, $status = 0) {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', 'apartment');
    $update_nodes = [];
    $create_nodes = [];
    $userid = \Drupal::currentUser()->id();

    // @todo Throw exception, delimiter not found.
    $delimiter = $this->getFileDelimiter($file->getFileUri());

    if (($handle = fopen($file->getFileUri(), "r")) !== FALSE) {
      $i = 0;

      // Each row represents old or new apartment node.
      while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== FALSE) {
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
          'uid' => $userid,
          'langcode' => $langcode,
          'status' => $status,
        ];

        // Create data array which is used to update or create node.
        foreach ($row as $key => $data) {
          if (!$data) {
            continue;
          }
          $type = $field_types[$key];

          /** @var \Drupal\asu_csv_import\ImportTypes\ImportType $data */
          $value = $this->createValue($data, $type);

          if ($value  && is_object($value)) {
            $value = $value->getImportValue();
          }
          else {
            $value = '';
          }

          $node_fields[$header[$key]] = $value;
        }

        // Update existing or create new node.
        if (isset($node_fields['nid'])) {
          $node = Node::load($node_fields['nid']);
          foreach ($node_fields as $key => $val) {
            if($key != 'empty'){
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
   * Create csv template file for user to download.
   */
  public function createCsvOutput(array $input) {
    // Write the file.
    $csv = fopen('php://temp/maxmemory:' . (5 * 1024 * 1024), 'r+');
    foreach ($input as $csv_row) {
      fputcsv($csv, $csv_row, ';', '"', '\\');
    }
    rewind($csv);
    $output = stream_get_contents($csv);
    fclose($csv);
    return $output;
  }

  /**
   * Creates rows that can be written into a csv file.
   *
   * @param Drupal\node\Entity\Node $node
   *   Node from form.
   * @param array $fields_in_order
   *   Array of fields that needs to be in the csv's header.
   *
   * @return array
   *   Array of rows to be written in csv
   */
  public function createCsvTemplateRows(Node $node, array $fields_in_order) {
    $rows = [];

    if ($node->field_apartments->isEmpty()) {
      return $rows;
    }

    foreach ($node->field_apartments as $apartment) {
      $row = [];
      $apt = Node::load($apartment->getValue()['target_id']);
      foreach ($fields_in_order as $field) {
        $data = NULL;
        // Csv can have "empty" between fields.
        if (!$apt) {
          continue;
        }
        try {
          if($field != 'empty'){
            $value = $apt->{$field}->value ? : $apt->{$field}->getString();
            $type = $apt->{$field}->getFieldDefinition()->getType();
            $data = $this->createValue($value, $type);
            if ($data) {
              $row[] = $data->getExportValue();
            }
            else {
              $row[] = '';
            }
          } else {
            $row[] = '-';
          }
        }
        catch (\Exception $e) {
          $row[] = '';
        }
      }
      $rows[] = $row;
    }
    return $rows;
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
      if ($type == 'asu_csv_import' && !$field->isEmpty()) {
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
      // csv may have empty values.
      if ($title == 'empty') {
        $field_types[] = 'empty';
        continue;
      }
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
    $value = NULL;
    switch ($type) {
      case 'integer':
        $value = new NumberType($data);
        break;

      case 'string':
      case 'string_long':
        $value = new TextType($data);
        break;

      case 'link':
        $value = new LinkType($data);
        break;

      case 'decimal':
        $value = new DecimalType($data);
        break;

      case 'boolean':
        $value = new BooleanType($data);
        break;

      case 'datetime':
        $value = new DateType($data);
        break;

      case 'empty':
        // In case of empty, just add single dash.
        $value = new TextType('-');
        break;

      default:
        $value = new TextType('');
    }

    return $value;
  }

  private function getFileDelimiter($filename, $checkLines = 2){
    $possibleDelimiters = [
      ',',
      ';',
      '|',
      ':'
    ];
    $results = [
      ',' => 0,
      ';' => 0,
      '|' => 0,
      ':' => 0
    ];
    $i = 0;
    $file = new \SplFileObject($filename);
    while($file->valid() && $i <= $checkLines) {
      $line = $file->fgets();
      foreach ($possibleDelimiters as $delimiter){
        $regExp = '/['.$delimiter.']/';
        $fields = preg_split($regExp, $line);
        if(count($fields) > 1){
            $results[$delimiter]++;
        }
      }
      $i++;
    }

    $results = array_keys($results, max($results));
    return $results[0];
  }


}
