<?php

namespace Drupal\asu_content\Controller;

use Drupal;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * An asu_content controller.
 */
class ApartmentContentCreateController extends ControllerBase {
  /**
   * Update project and apartment fields values.
   *
   * @param int $id
   * @return void
   */
  public function content($id = null) {
    $projects = $this->get_content('project');
    $apartments = $this->get_content('apartment');
    $page_title = "Updated all project & apartment nodes.";

    if (count($projects) > 0 && ($projects[$id] ?? null)) {
      $page_title = "Updated project (id: $id) node and its apartments nodes.";
      $this->update_project_content([$projects[$id]]);
      $apartments = $this->get_apartment_nodes_by_project_id($id);
      $this->update_apartment_content($apartments);
    } else {
      $this->update_project_content($projects);
      $this->update_apartment_content($apartments);
    }

    $build = [
      '#markup' => "<h1 class='wrapper wrapper--mw-1200'>$page_title</h1>",
    ];

    return $build;
  }

  /**
   * Generate image files from image URLs.
   *
   * @return array
   */
  private function get_generated_images() {
    $host = \Drupal::request()->getSchemeAndHttpHost();

    $external_images = [
      'https://images.pexels.com/photos/1643384/pexels-photo-1643384.jpeg',
      'https://images.pexels.com/photos/1571468/pexels-photo-1571468.jpeg',
      'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg',
      'https://images.pexels.com/photos/813692/pexels-photo-813692.jpeg',
      'https://images.pexels.com/photos/279719/pexels-photo-279719.jpeg',
      'https://images.pexels.com/photos/275484/pexels-photo-275484.jpeg',
      'https://images.pexels.com/photos/439227/pexels-photo-439227.jpeg',
    ];

    $this->save_external_images($external_images);

    $internal_images_paths = [];

    for ($i = 0; $i < count($external_images); $i++) {
      $internal_images_paths[] = "$host/sites/default/files/generated_apartment_image_$i.jpeg";
    }

    $internal_images_data = [];

    foreach ($internal_images_paths as $image) {
      $image = $this->get_file_data($image);
      $internal_images_data[] = $image;
    }

    return $internal_images_data;
  }

  /**
   * Fill out project node fields and save it.
   *
   * @param array $projects
   * @return void
   */
  private function update_project_content($projects) {
    $images = $this->get_generated_images();

    /** Node $project */
    foreach ($projects as $project) {
      shuffle($images);
      $project->field_shared_apartment_images = array_slice($images, 0, 3);
      shuffle($images);
      $project->field_images = array_slice($images, 0, 3);

      $project->save();
    }
  }

  /**
   * Fill out apartment node fields and save it.
   *
   * @param array $apartments
   * @return void
   */
  private function update_apartment_content($apartments) {
    $images = $this->get_generated_images();

    /** Node $apartment */
    foreach ($apartments as $apartment) {
      $apartment->field_apartment_state_of_sale = 'open_for_applications';
      shuffle($images);
      $apartment->field_images = array_slice($images, 0, 3);

      if (rand(0, 9) % 3 === 0) {
        $apartment->field_publish_on_oikotie = 1;
        $apartment->field_publish_on_etuovi = 1;
      }

      $apartment->save();
    }
  }

  /**
   * Get all nodes from content type.
   *
   * @param string $content_type
   * @return array
   */
  private function get_content($content_type) {
    return \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['type' => $content_type]);
  }

  /**
   * Get all apartment nodes referenced by project id.
   *
   * @param string $id
   * @return array
   */
  private function get_apartment_nodes_by_project_id($id) {
    $apartments = $this->get_content('apartment');
    $apartments_stack = [];

    foreach ($apartments as $key => $apartment) {
      $parent_node_results = \Drupal::entityTypeManager()
      ->getListBuilder('node')
      ->getStorage()
      ->loadByProperties([
          'type' => 'project',
          'status' => 1,
          'field_apartments' => $apartment->id(),
        ]
      );

      if (key($parent_node_results) === (int) $id) {
        $apartments_stack[$key] = $key;
      }
    }

    return array_intersect_key($apartments, array_flip($apartments_stack));
  }

  /**
   * Save image URLs as Drupal files.
   *
   * @param array $images
   * @return void
   */
  private function save_external_images($images) {
    if (empty($images)) {
      return;
    }

    foreach ($images as $key => $image) {
      $image = file_get_contents($image);
      file_save_data($image, "public://generated_apartment_image_$key.jpeg", FileSystemInterface::EXISTS_REPLACE);
    }
  }

  /**
   * Return file data from file absolute url.
   *
   * @param string $file_url
   * @return array
   */
  private function get_file_data($file_url) {
    $file_name = Drupal::service('file_system')->basename($file_url);
    $target_file = Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['filename' => $file_name]);
    $file_data = reset($target_file);

    if (!$file_data) {
      return FALSE;
    }

    return $file_data;
  }

}
