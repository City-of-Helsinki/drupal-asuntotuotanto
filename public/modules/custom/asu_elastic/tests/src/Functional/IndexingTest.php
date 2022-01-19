<?php

declare(strict_types = 1);

namespace Drupal\Tests\asu_elastic\Functional;

use Drupal\KernelTests\AssertLegacyTrait;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\RandomGeneratorTrait;
use Drupal\Tests\UiHelperTrait;
use PHPUnit\Framework\TestCase;
use weitzman\DrupalTestTraits\DrupalTrait;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\Entity\UserCreationTrait;
use weitzman\DrupalTestTraits\GoutteTrait;

/**
 * Test elasticsearch indexing.
 *
 * @group asu_elastic
 */
final class IndexingTest extends TestCase {

  use DrupalTrait;
  use GoutteTrait;
  use NodeCreationTrait;
  use UserCreationTrait;
  use TaxonomyCreationTrait;
  use UiHelperTrait;

  // The entity creation traits need this.
  use RandomGeneratorTrait;

  // Core is still using this in role creation, so it must be included here when
  // using the UserCreationTrait.
  use AssertLegacyTrait;

  /**
   * The database prefix of this test run.
   *
   * @var string
   */
  protected $databasePrefix;

  /**
   * The base URL.
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupDrupal();
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    parent::tearDown();
    $this->tearDownDrupal();
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareRequest() {
  }

  /**
   * Make sure indexed data is in correct format.
   */
  public function testElasticSearchIndexing() {
    // $index = Index::load('apartment');
    // $index->clear();
    // $server = $index->getServerInstance();
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

    // $server->getBackend()->updateIndex($index);
    // $dataSource = $index->getDataSourceIds();
    // $index->indexItems(-1, reset($dataSource));
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
