<?php

declare(strict_types = 1);

namespace Drupal\Tests\asu_elastic\Functional;

use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
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
    /** @var \Drupal\search_api\Entity\Server $server */

    $elastic_url = Settings::get('ASU_ELASTICSEARCH_ADDRESS');

    $client = $this->container->get('http_client_factory')
      ->fromOptions(['base_uri' => 'http://elastic:9200']);

    $result = json_decode(
      $client->request('GET', '/_search')
        ->getBody()
        ->getContents(),

      TRUE
    );

    $this->assertArrayHasKey('hits', $result);
    $this->assertEmpty($result['hits']['hits']);

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
    $construction_material = $this->createTerm(
      Vocabulary::load('construction_materials'),
      ['Puu']
    );

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
