<?php

declare(strict_types = 1);

namespace Drupal\Tests\asu_elastic\Functional;

use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Server;
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
    $servers = Server::loadMultiple();
    $this->assertNotEmpty($servers, 'We have at least one server');

    /** @var \Drupal\search_api\Entity\Server $server */
    $server = reset($servers);

    $indexes = $server->getIndexes();
    $this->assertNotEmpty($indexes, 'We have at least one index');

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = reset($indexes);
    $index->clear();
    $client = $this->container->get('http_client');
    $result = json_decode(
      $client->request('GET', 'http://elastic:9200/_search')->getBody()->getContents(),
      TRUE
    );

    $this->assertArrayHasKey('hits', $result, 'We get correct response');
    $this->assertEmpty($result['hits']['hits'], 'No hits at this point');

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

    sleep(2);

    $new_result = json_decode(
      $client->request('GET', 'http://elastic:9200/_search')->getBody()->getContents(),
      TRUE
    );

    $this->assertNotEmpty($new_result['hits']['hits'], 'Index should have hits');

    $data = $new_result['hits']['hits'][0]['_source'];

    // Single values should not be inside array.
    $this->assertIsNotArray($data['title'],
      'Single values should not be in array');
    $this->assertIsString($data['title']);

    $this->assertIsNotArray($data['has_terrace'],
      'Boolean values should be booleans');
    $this->assertFalse($data['has_terrace']);

    $this->assertIsArray($data['project_heating_options'],
      'Multivalued fields should be in arrays');
    $this->assertNotEmpty($data['project_heating_options']);
    $this->assertIsString($data['project_heating_options'][0]);

    $this->assertIsNotArray($data['project_virtual_presentation_url']);
    $this->assertIsString($data['project_virtual_presentation_url']);

    // $this->assertNotEmpty($data['application_url']);
    // $this->assertNotEmpty($data['application_url']);
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
      'field_apartment_number' => 'A1',
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
      'title' => 'Uusi projekti',
      'body' => 'This is the description of the project',
      'field_street_address' => 'Testaajankatu 3',
      'field_housing_company' => 'Taloyhtiö Yritys Oy',
      'field_construction materials' => [$construction_material],
      'field_heating_options' => [$heating_option],
      'field_apartments' => [$apartment->ID()],
      'field_application_start_time' => (new \DateTime('yesterday'))->format('Y-m-d H:i:s'),
      'field_application_end_time' => (new \DateTime('tomorrow'))->format('Y-m-d H:i:s'),
    ];
  }

}
