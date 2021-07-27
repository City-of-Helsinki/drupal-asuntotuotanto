<?php

namespace Drupal\asu_content\Controller;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * An asu_content controller.
 */
class ApartmentContentCreateController extends ControllerBase {

  /**
   * Update project and apartment fields values.
   */
  public function content() {
    $projects = $this->getContent('project');
    $apartments = $this->getContent('apartment');
    $page_title = "Updated all project & apartment nodes.";

    $this->updateProjectContent($projects);
    $this->updateApartmentContent($apartments);

    $build = [
      '#markup' => "<h1 class='wrapper wrapper--mw-1200'>$page_title</h1>",
    ];

    return $build;
  }

  /**
   * Generate image files from image URLs.
   *
   * @return array
   *
   *   Will return an array of images in File format.
   */
  private function getGeneratedImages() {
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

    $this->saveExternalImages($external_images);

    $internal_images_paths = [];

    for ($i = 0; $i < count($external_images); $i++) {
      $internal_images_paths[] = "$host/sites/default/files/generated_apartment_image_$i.jpeg";
    }

    $internal_images_data = [];

    foreach ($internal_images_paths as $image) {
      $image = $this->getFileData($image);
      $internal_images_data[] = $image;
    }

    return $internal_images_data;
  }

  /**
   * Fill out project node fields and save it.
   *
   * @param array $projects
   *
   *   Array of nodes.
   */
  private function updateProjectContent(array $projects) {
    $images = $this->getGeneratedImages();

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
   *
   *   Array of nodes.
   */
  private function updateApartmentContent(array $apartments) {
    $images = $this->getGeneratedImages();

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
   *
   *   Name of the content type.
   *
   * @return array
   *
   *   Will return an array of nodes for that content type.
   */
  private function getContent($content_type) {
    return \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['type' => $content_type]);
  }

  /**
   * Get all apartment nodes referenced by project id.
   *
   * @param string $id
   *
   *   Project id.
   *
   * @return array
   *
   *   Will return an array of apartment nodes by project id.
   */
  private function getApartmentNodesByProjectId($id) {
    $apartments = $this->getContent('apartment');
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
   *
   *   Array of external image URLs.
   */
  private function saveExternalImages(array $images) {
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
   *
   *   Absolute file URL.
   *
   * @return array
   *
   *   Will return an array of image data.
   */
  private function getFileData(string $file_url) {
    $file_name = \Drupal::service('file_system')->basename($file_url);
    $target_file = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['filename' => $file_name]);
    $file_data = reset($target_file);

    if (!$file_data) {
      return FALSE;
    }

    return $file_data;
  }

}
