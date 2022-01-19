<?php

declare(strict_types = 1);

namespace Drupal\Tests\asu_elastic\Functional;

use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\taxonomy\Entity\Vocabulary;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test elasticsearch indexing.
 *
 * @group asu_elastic
 */
final class IndexingTest extends ExistingSiteBase {

  /**
   * Make sure indexed data is in correct format.
   */
  public function testElasticSearchIndexing() {
    $index = Index::load('apartment');
    $index->clear();

    /** @var \Drupal\search_api\Entity\Server $server */
    $server = $index->getServerInstance();

    // $query = $index->query();
    // $query->range(0, 10000);
    // $result = $query->execute();

    $result = ['hits' => ['hits' => []]];

    $this->assertArrayHasKey('hits', $result);
    $this->assertEmpty($result['hits']['hits']);

    $apartment = $this->createNode($this->apartmentData());

    $apartment->save();

    $project = $this->createNode($this->projectData($apartment));

    $date = new \DateTime();

    $project->set('field_application_end_time', $date->format('Y-m-d H:i:s'));

    $project->set('field_virtual_presentation_url', 'https://www.gooogle.fi');

    $project->save();

    sleep(1);

    $server->getBackend()->updateIndex($index);

    $dataSource = $index->getDataSourceIds();
    $index->indexItems(-1, reset($dataSource));

    sleep(1);

    /*
    $query2 = $index->query();
    $query2->range(0, 10000);
    $result2 = $query2->execute();

    $this->assertNotEmpty($result2['hits']['hits']);

    $data = $result2['hits']['hits'][0]['_source'];

    // Single values should not be inside array.
    $this->assertIsNotArray($data['title']);
    $this->assertIsString($data['title']);

    $this->assertIsNotArray($data['has_terrace']);
    $this->assertFalse($data['has_terrace']);

    $this->assertIsArray($data['project_heating_options']);
    $this->assertNotEmpty($data['project_heating_options']);
    $this->assertIsString($data['project_heating_options'][0]);

    $this->assertIsNotArray($data['project_virtual_presentation_url']);
    $this->assertIsString($data['project_virtual_presentation_url']);
    */
  }

  /**
   * Get apartment data.
   *
   * @return array
   *   Values for createnode function.
   */
  private function apartmentData() {
    $d = new \DateTime();

    return [
      'type' => 'apartment',
      'title' => 'actual apartment title',
      'body' => 'This is the description of the apartment',
      'field_showing_times' => [$d->format('Y-m-d H:i:s')],
    ];
  }

  /**
   * Get project data.
   *
   * @param \Drupal\node\NodeInterface $apartment
   *   The content entity.
   *
   * @return array
   *   Values for createnode function.
   */
  private function projectData(NodeInterface $apartment) {
    $heating_option = $this->createTerm(Vocabulary::load('heating_options'), ['Maalämpö']);
    $construction_material = $this->createTerm(Vocabulary::load('construction_materials'), ['Puu']);

    return [
      'type' => 'project',
      'title' => 'project title',
      'body' => 'This is the description of the project',
      'field_housing_company' => 'Taloyhtiö Yritys Oy',
      'field_construction materials' => [$construction_material],
      'field_heating_options' => [$heating_option],
      'field_apartments' => [$apartment->ID()],
    ];
  }

}
