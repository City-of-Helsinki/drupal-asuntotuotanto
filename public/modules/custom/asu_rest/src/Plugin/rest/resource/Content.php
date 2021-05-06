<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Provides a resource to subscribe to mailing list.
 *
 * @RestResource(
 *   id = "asu_content",
 *   label = @Translation("Content"),
 *   uri_paths = {
 *     "canonical" = "/content/{id}",
 *     "https://www.drupal.org/link-relations/create" = "/content/{id}"
 *   }
 * )
 */
final class Content extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @param string $id
   *   Data required by the endpoint.
   *
   * @return Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function get(string $id): ModifiedResourceResponse {
    if (!$node = Node::load($id)) {
      return new ModifiedResourceResponse([], 404);
    }

    $cta_image_file_target_id = $node->get('field_images')->getValue()[0]['target_id'];
    $variables['cta_image'] = $this->load_responsive_image_style($cta_image_file_target_id, 'image__3_2');

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
      $application_start_time_timestamp = $this->format_date_to_unix_timestamp($application_start_time_value);
      $application_end_time_value = $parent_node->get('field_application_end_time')->value;
      $application_end_time_timestamp = $this->format_date_to_unix_timestamp($application_end_time_value);
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
      $apartment_attachments = []; # $node->get('field_apartment_attachments')->getValue();
      $attachments_stack = [];
      $estimated_completion_date = new \DateTime($parent_node->get('field_estimated_completion_date')->value);

      $site_owner = Term::load($parent_node->get('field_site_owner')->target_id)->name->value;
      $site_renter = $parent_node->get('field_site_renter')->value;

      foreach ($services as $key => $service) {
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

      foreach ($apartment_attachments as $key => $attachment) {
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

      foreach ($project_attachments as $key => $attachment) {
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

    $nodeData = $node->toArray();
    $data = [];
    foreach ($nodeData as $field => $value) {
      $data[$field] = $node->{$field}->value;
    }

    $data['id'] = $node->id();
    $data['application_start_time'] = $this->format_timestamp_to_custom_format($application_start_time_timestamp);
    $data['application_end_time'] = $this->format_timestamp_to_custom_format($application_end_time_timestamp);
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
    $data['attachments'] = $attachments_stack ?? NULL;
    $data['estimated_completion_date'] = $estimated_completion_date->format('m/Y') ?? NULL;
    $data['site_owner'] = $site_owner ?? NULL;
    $data['site_renter'] = $site_renter ?? NULL;

    $data['id'] = $node->id();
    return new ModifiedResourceResponse($data);
  }


  /**
   * Custom function format_date_to_unix_timestamp().
   */
  private function format_date_to_unix_timestamp($string) {
    $value = $string;
    $date = new \DateTime($value);
    $timestamp = $date->format('U');

    return $timestamp;
  }

  /**
   * Custom function format_timestamp_to_custom_format().
   */
  private function format_timestamp_to_custom_format($timestamp, $format = 'short') {
    return \Drupal::service('date.formatter')->format($timestamp, $format);
  }

  /**
   * Custom load_responsive_image_style().
   */
  private function load_responsive_image_style($image_file_target_id, $responsive_image_style_id) {
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
   * Custom get_apartment_application_status().
   */
  private function get_apartment_application_status($nid) {
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

}
