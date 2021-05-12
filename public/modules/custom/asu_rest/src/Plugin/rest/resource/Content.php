<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a resource to subscribe to mailing list.
 *
 * @RestResource(
 *   id = "asu_content",
 *   label = @Translation("Content"),
 *   uri_paths = {
 *     "canonical" = "/content/{type}/{id}",
 *     "https://www.drupal.org/link-relations/create" = "/content/{type}/{id}"
 *   }
 * )
 */
final class Content extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @param string $type
   *   Content type.
   * @param string $id
   *   Data required by the endpoint.
   *
   * @return Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function get(string $type, string $id): ModifiedResourceResponse {
    if (!$node = Node::load($id)) {
      return new ModifiedResourceResponse([], 404);
    }

    if ($node->bundle() != $type) {
      return new ModifiedResourceResponse([], 404);
    }

    if ($node->bundle() == 'apartment') {
      $data = $this->getApartmentFields($node);
    }
    else {
      $data = $this->getProjectFields($node);
    }

    return new ModifiedResourceResponse($data);
  }

  /**
   * Custom function formatDateToUnixTimestamp().
   */
  private function formatDateToUnixTimestamp($string) {
    $value = $string;
    $date = new \DateTime($value);
    $timestamp = $date->format('U');

    return $timestamp;
  }

  /**
   * Custom function formatTimestampToCustomFormat().
   */
  private function formatTimestampToCustomFormat($timestamp, $format = 'short') {
    return \Drupal::service('date.formatter')->format($timestamp, $format);
  }

  /**
   * Custom loadResponsiveImageStyle().
   */
  private function loadResponsiveImageStyle($image_file_target_id, $responsive_image_style_id) {
    if (!$image_file_target_id && !$responsive_image_style_id) {
      return NULL;
    }

    $file = File::load($image_file_target_id);

    if (!$file) {
      return NULL;
    }

    $file_uri = $file->getFileUri();
    $image = \Drupal::service('image.factory')->get($file_uri);

    if ($image->isValid()) {
      $image_height = $image->getHeight();
      $image_width = $image->getWidth();
    }
    else {
      $image_height = NULL;
      $image_width = NULL;
    }

    $image_build = [
      '#theme' => 'responsive_image',
      '#width' => $image_width,
      '#height' => $image_height,
      '#responsive_image_style_id' => $responsive_image_style_id,
      '#uri' => $file_uri,
    ];

    $renderer = \Drupal::service('renderer');
    $renderer->addCacheableDependency($image_build, $file);

    return $image_build;
  }

  /**
   * Custom getApartmentApplicationStatus().
   */
  private function getApartmentApplicationStatus($nid) {
    $application_status_mapping = [
      "NONE" => t('No applicants'),
      "LOW" => t('Few applicants'),
      "MEDIUM" => t('A little applicants'),
      "HIGH" => t('A lot of applicants'),
    ];

    // @todo Update this value with dynamic status from API.
    $application_status = 'NONE';

    return [
      "status" => strtolower($application_status),
      "label" => $application_status_mapping[$application_status],
    ];
  }

  /**
   * Get apartment fields.
   */
  private function getApartmentFields($node) {
    $data = [];
    $cta_image_file_target_id = $node->get('field_images')->getValue()[0]['target_id'];

    $image = $this->loadResponsiveImageStyle($cta_image_file_target_id, 'image__3_2');

    $data['cta_image_url'] = str_replace('http://', 'http://Asu:asunnot_2020@', file_create_url($image['#uri']));

    // @ todo: get alt text
    // $data['cta_image']['alt'] = 'Alt text here';
    $parent_node_results = \Drupal::entityTypeManager()
      ->getListBuilder('node')
      ->getStorage()
      ->loadByProperties([
        'type' => 'project',
        'status' => 1,
        'field_apartments' => $node->id(),
      ]
      );

    if ($parent_node_results) {
      $parent_node_nid = key($parent_node_results);
      $parent_node = Node::load($parent_node_nid);
      $is_application_period_active = FALSE;
      $is_application_period_in_the_past = FALSE;

      $application_start_time_value = $parent_node->get('field_application_start_time')->value;
      $application_start_time_timestamp = $this->formatDateToUnixTimestamp($application_start_time_value);
      $application_end_time_value = $parent_node->get('field_application_end_time')->value;
      $application_end_time_timestamp = $this->formatDateToUnixTimestamp($application_end_time_value);
      $current_timestamp = time();

      if ($current_timestamp > $application_start_time_timestamp && $current_timestamp < $application_end_time_timestamp) {
        $is_application_period_active = TRUE;
      }

      if ($current_timestamp > $application_end_time_timestamp) {
        $is_application_period_in_the_past = TRUE;
      }

      $street_address = $parent_node->get('field_street_address')->value;
      $postal_code = $parent_node->get('field_postal_code')->value;
      $city = $parent_node->get('field_city')->value;
      $district = Term::load($parent_node->get('field_district')->target_id)->name->value;
      $ownership_type = Term::load($parent_node->get('field_ownership_type')->target_id)->name->value;

      $project_description = $parent_node->get('field_project_description')->value;
      $project_area_description = $parent_node->get('field_project_area_description')->value;
      $building_type = Term::load($parent_node->get('field_building_type')->target_id)->name->value;
      $energy_class = Term::load($parent_node->get('field_energy_class')->target_id)->name->value;
      $accessibility = $parent_node->get('field_project_accessibility')->value;
      $services = $parent_node->get('field_services')->getValue();
      $services_url = $parent_node->get('field_services_url')->getValue()[0];
      $services_stack = [];
      $project_attachments = $parent_node->get('field_project_attachments')->getValue();
      // $node->get('field_apartment_attachments')->getValue();
      $apartment_attachments = [];
      $attachments_stack = [];
      $estimated_completion_date = new \DateTime($parent_node->get('field_estimated_completion_date')->value);

      $site_owner = Term::load($parent_node->get('field_site_owner')->target_id)->name->value;
      $site_renter = $parent_node->get('field_site_renter')->value;

      foreach ($services as $service) {
        $term_id = $service['term_id'];

        if ($term_id !== '0') {
          $service_name = Term::load($term_id)->name->value;
          $service_distance = $service['distance'];

          $services_stack[] = [
            'name' => $service_name,
            'distance' => $service_distance,
          ];
        }
      }

      foreach ($apartment_attachments as $attachment) {
        $target_id = $attachment['target_id'];
        $file = File::load($target_id);

        if ($file) {
          $description = $attachment['description'];
          $file_name = $file->getFilename();
          $file_size = format_size($file->getSize());
          $file_uri = file_create_url($file->getFileUri());

          array_push($attachments_stack, [
            'description' => $description,
            'name' => $file_name,
            'size' => $file_size,
            'uri' => $file_uri,
          ]);
        }
      }

      foreach ($project_attachments as $attachment) {
        $target_id = $attachment['target_id'];
        $file = File::load($target_id);

        if ($file) {
          $description = $attachment['description'];
          $file_name = $file->getFilename();
          $file_size = format_size($file->getSize());
          $file_uri = file_create_url($file->getFileUri());

          array_push($attachments_stack, [
            'description' => $description,
            'name' => $file_name,
            'size' => $file_size,
            'uri' => $file_uri,
          ]);
        }
      }

    }

    $images = [];

    foreach ($node->field_images->getValue() as $value) {
      $image = $this->loadResponsiveImageStyle($value['target_id'], 'image__3_2');
      // @ todo: remove replace
      $images[] = str_replace('http://', 'http://Asu:asunnot_2020@', file_create_url($image['#uri']));
    }
    foreach ($parent_node->field_shared_apartment_images->getValue() as $value) {
      $image = $this->loadResponsiveImageStyle($value['target_id'], 'image__3_2');
      // @ todo: remove replace
      $images[] = str_replace('http://', 'http://Asu:asunnot_2020@', file_create_url($image['#uri']));
    }

    $nodeData = $node->toArray();

    foreach ($nodeData as $field => $value) {
      $data[$field] = $node->{$field}->value;
    }

    $data['id'] = $node->id();
    $data['images'] = $images;

    $data['application_start_time'] = $this->formatTimestampToCustomFormat($application_start_time_timestamp);
    $data['application_end_time'] = $this->formatTimestampToCustomFormat($application_end_time_timestamp);

    $data['is_application_period_active'] = $is_application_period_active;
    $data['is_application_period_in_the_past'] = $is_application_period_in_the_past;
    $data['district'] = $district ?? NULL;
    $data['address'] = "$street_address, $postal_code $city" ?? NULL;
    $data['ownership_type'] = $ownership_type ?? NULL;
    $data['accessibility'] = $accessibility ?? NULL;
    $data['project_description'] = $project_description ?? NULL;
    $data['project_area_description'] = $project_area_description ?? NULL;
    $data['building_type'] = $building_type ?? NULL;
    $data['energy_class'] = $energy_class ?? NULL;

    $data['services'] = $services_stack ?? NULL;

    $data['services_url'] = $services_url ?? NULL;

    // @todo Attachements.
    $data['attachments'] = $attachments_stack ?? NULL;

    $data['estimated_completion_date'] = $estimated_completion_date->format('m/Y') ?? NULL;

    $data['site_owner'] = $site_owner ?? NULL;
    $data['site_renter'] = $site_renter ?? NULL;

    return $data;
  }

  /**
   * Get project fields.
   */
  private function getProjectFields($node) {
    $data = [];

    $apartmentsData = $node->get('field_apartments')->getValue();

    $apartments = [];
    $apartment_structures = [];
    $apartment_living_area_sizes = [];
    $apartment_sales_prices = [];
    $apartment_debt_free_sales_prices = [];

    foreach ($apartmentsData as $apartment) {
      $apartment_target_id = $apartment['target_id'];
      $apartment_node = Node::load($apartment_target_id);
      $apartment_sales_price = $apartment_node->get('field_sales_price')->value;
      $apartment_debt_free_sales_price = $apartment_node->get('field_debt_free_sales_price')->value;
      $apartment_living_area_size = $apartment_node->get('field_living_area')->value;
      $apartment_structure = $apartment_node->get('field_apartment_structure')->value;
      $application_url = $apartment_node->get('field_application_url')->getValue()[0]['uri'];
      $number = $apartment_node->get('field_apartment_number')->value;
      $floor = $apartment_node->get('field_floor')->value;

      array_push($apartment_sales_prices, $apartment_sales_price);
      array_push($apartment_debt_free_sales_prices, $apartment_debt_free_sales_price);
      array_push($apartment_living_area_sizes, $apartment_living_area_size);
      array_push($apartment_structures, $apartment_structure);

      $apartments[] = [
        'id' => $apartment_target_id,
        'number' => $number,
        'floor' => $floor,
        'application_url' => $application_url,
        'sales_price' => $apartment_sales_price,
        'debt_free_sales_price' => $apartment_debt_free_sales_price,
        'living_area_size' => $apartment_living_area_size,
        'application_url' => $application_url,
        'structure' => $apartment_structure,
      ];
    }

    sort($apartment_structures);

    $apartment_debt_free_sales_prices_minmax = [
      "min" => number_format(min($apartment_debt_free_sales_prices), 2, ',', '.'),
      "max" => number_format(max($apartment_debt_free_sales_prices), 2, ',', '.'),
    ];

    $apartment_sales_prices_minmax = [
      "min" => number_format(min($apartment_sales_prices), 2, ',', '.'),
      "max" => number_format(max($apartment_sales_prices), 2, ',', '.'),
    ];

    $apartment_living_area_sizes_minmax = [
      "min" => number_format(min($apartment_living_area_sizes), 1, ',', NULL),
      "max" => number_format(max($apartment_living_area_sizes), 1, ',', NULL),
    ];

    $apartment_debt_free_sales_prices_string = $apartment_debt_free_sales_prices_minmax['min'] . " € - " . $apartment_debt_free_sales_prices_minmax['max'] . " €";
    $apartment_sales_prices_string = $apartment_sales_prices_minmax['min'] . " € - " . $apartment_sales_prices_minmax['max'] . " €";
    $apartment_living_area_sizes_string = $apartment_living_area_sizes_minmax['min'] . " - " . $apartment_living_area_sizes_minmax['max'];

    $services = $node->get('field_services')->getValue();
    $services_stack = [];

    foreach ($services as $service) {
      $term_id = $service['term_id'];

      if ($term_id !== '0') {
        $service_name = Term::load($term_id)->name->value;
        $service_distance = $service['distance'];

        $services_stack[] = [
          'name' => $service_name,
          'distance' => $service_distance,
        ];
      }
    }

    $project_attachments = $node->get('field_project_attachments')->getValue();
    $attachments_stack = [];

    foreach ($project_attachments as $key => $attachment) {
      $target_id = $attachment['target_id'];
      $file = File::load($target_id);
      $description = $attachment['description'];
      $file_name = $file->getFilename();
      $file_size = format_size($file->getSize());
      $file_uri = file_create_url($file->getFileUri());

      $attachments_stack[$key] = [
        'description' => $description,
        'name' => $file_name,
        'size' => $file_size,
        'uri' => $file_uri,
      ];
    }

    $application_start_time_value = $node->get('field_application_start_time')->value;
    $application_start_time_timestamp = $this->formatDateToUnixTimestamp($application_start_time_value);
    $application_end_time_value = $node->get('field_application_end_time')->value;
    $application_end_time_timestamp = $this->formatDateToUnixTimestamp($application_end_time_value);

    $estimated_completion_date = new \DateTime($node->get('field_estimated_completion_date')->value);
    $is_application_period_active = FALSE;
    $current_timestamp = time();

    if ($current_timestamp >= $application_start_time_timestamp && $current_timestamp <= $application_end_time_timestamp) {
      $is_application_period_active = TRUE;
    }

    $nodeData = $node->toArray();

    $images = [];
    foreach ($node->field_images->getValue() as $key => $value) {
      $image = $this->loadResponsiveImageStyle($value['target_id'], 'image__3_2');
      $images[] = str_replace('http://', 'http://Asu:asunnot_2020@', file_create_url($image['#uri']));
    }

    foreach ($nodeData as $field => $value) {
      $data[$field] = $node->{$field}->value;
    }

    if (isset($data['field_tasks'])) {
      unset($data['field_tasks']);
    }

    $data['id'] = $node->id();
    $data['images'] = $images;
    $data['apartments'] = $apartments;

    $data['application_start_time'] = $this->formatTimestampToCustomFormat($application_start_time_timestamp);
    $data['application_end_time'] = $this->formatTimestampToCustomFormat($application_end_time_timestamp);
    $data['apartments_count'] = count($apartments);
    $data['apartment_sales_prices'] = $apartment_sales_prices_string;
    $data['apartment_debt_free_sales_prices'] = $apartment_debt_free_sales_prices_string;
    $data['apartment_structures'] = implode(", ", array_unique($apartment_structures));
    $data['apartment_living_area_sizes_m2'] = $apartment_living_area_sizes_string;
    // @todo Attachments.
    $data['attachments'] = $attachments_stack ?? NULL;
    $apartments = $apartments;
    // @todo Services.
    $data['services'] = $services_stack ?? NULL;
    $data['estimated_completion_date'] = $estimated_completion_date->format('m/Y') ?? NULL;
    $data['is_application_period_active'] = $is_application_period_active;

    return $data;
  }

}
