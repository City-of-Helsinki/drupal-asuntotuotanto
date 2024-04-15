<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Functional;

use Drupal\asu_content\ProjectUpdater;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test Project & application node automated state change.
 *
 * @group asu_content
 */
final class ContentTest extends ExistingSiteBase {

  /**
   * Automated state changes from pre-marketing to applicable to reserved.
   */
  public function testApplicationStateChanges() {
    $apartmentData = $this->apartmentData('apartment_for_sale');
    $apartment = $this->createNode($apartmentData);
    $projectData = $this->projectData($apartment, 'pre_marketing');
    $project = $this->createNode($projectData);

    // This is done by cron.
    $projectUpdater = new ProjectUpdater();
    $projectUpdater->updateProjectStateToForSale($project);

    $newProject = Node::load($project->id());
    $newApartment = Node::load($apartment->id());

    // Assert state change.
    $this->assertEquals(
      $newProject->field_state_of_sale->target_id,
      'for_sale',
      'During application period,
       project should be open for sale'
    );
    $this->assertEquals(
      $newApartment->field_apartment_state_of_sale->target_id,
      'open_for_applications',
      'During application period,
      apartment should be open for application'
    );

    // Update application end time to be in the past.
    $newProject->set(
      'field_application_end_time',
      (new \DateTime('yesterday'))->format('Y-m-d H:i:s')
    );
    $newProject->save();

    $newProject = Node::load($newProject->id());
    $projectUpdater->updateProjectStateToReserved($newProject);

    // Assert state when application end time is in the past.
    $this->assertEquals(
      $newProject->field_state_of_sale->target_id,
      'processing',
      'After the application period, project should be processing'
    );
    $this->assertTrue(
      in_array(
        $newApartment->field_apartment_state_of_sale->target_id,
        ['reserved', 'reserved_haso', 'free_for_reservations']
      ),
      'After the application period,
      apartment should be reserved or free for reservations'
    );

  }

  /**
   * Get apartment data.
   *
   * @param string $stateOfSale
   *   Apartment state of sale.
   *
   * @return array
   *   Values for createnode function.
   */
  private function apartmentData(string $stateOfSale) {
    $d = new \DateTime();

    return [
      'type' => 'apartment',
      'status' => TRUE,
      'title' => 'actual apartment title',
      'field_apartment_number' => 'A1',
      'body' => 'This is the description of the apartment',
      'field_showing_times' => [$d->format('Y-m-d H:i:s')],
      'field_apartment_state_of_sale' => $stateOfSale,
    ];
  }

  /**
   * Get project data.
   *
   * @param \Drupal\node\NodeInterface $apartment
   *   The content entity.
   * @param string $stateOfSale
   *   State of sale for the new project.
   *
   * @return array
   *   Values for createnode function.
   */
  private function projectData(NodeInterface $apartment, string $stateOfSale) {
    return [
      'type' => 'project',
      'status' => TRUE,
      'title' => 'Uusi projekti',
      'body' => 'This is the description of the project',
      'field_street_address' => 'Testaajankatu 3',
      'field_housing_company' => 'Taloyhtiö Yritys Oy',
      'field_apartments' => [$apartment->ID()],
      'field_application_start_time' => (new \DateTime('yesterday'))->format('Y-m-d H:i:s'),
      'field_application_end_time' => (new \DateTime('tomorrow'))->format('Y-m-d H:i:s'),
      'field_apartment_state_of_sale' => $stateOfSale,
    ];
  }

}
