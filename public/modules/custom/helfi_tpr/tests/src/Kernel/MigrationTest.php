<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tpr\Kernel;

use Drupal\helfi_tpr\Entity\Service;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\Tests\helfi_api_base\Kernel\MigrationTestBase;
use GuzzleHttp\Psr7\Response;

/**
 * Tests unit migration.
 *
 * @group helfi_tpr
 */
class MigrationTest extends MigrationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'address',
    'text',
    'helfi_tpr',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('tpr_unit');
    $this->installEntitySchema('tpr_service');
    $this->installConfig(['helfi_tpr']);
  }

  /**
   * Create the unit migration.
   *
   * @return array
   *   List of entities.
   */
  protected function createUnitMigration() : array {
    $units = $this->getFixture('helfi_tpr', 'unit.json');
    $responses = [
      new Response(200, [], $units),
    ];

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('tpr_unit');
    return Unit::loadMultiple();
  }

  /**
   * Create the service migration.
   *
   * @return array
   *   List of entities.
   */
  protected function createServiceMigration() : array {
    $services = $this->getFixture('helfi_tpr', 'services.json');
    $responses = [
      new Response(200, [], $services),
    ];

    foreach (json_decode($services, TRUE) as $id => $service) {
      foreach (['fi', 'en', 'sv'] as $language) {
        $service += [
          'description_short' => $language . ' Description short ' . $id,
          'description_long' => $language . ' Description long ' . $id,
        ];
        $responses[] = new Response(200, [], json_encode($service));
      }
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('tpr_service');
    return Service::loadMultiple();
  }

  /**
   * Tests unit migration.
   */
  public function testUnitMigration() : void {
    $entities = $this->createUnitMigration();
    $this->assertCount(6, $entities);

    foreach (['en', 'sv'] as $langcode) {
      foreach ($entities as $entity) {
        $this->assertEquals($entity->getTranslation($langcode)->language()->getId(), $langcode);
      }
    }
  }

  /**
   * Tests service migration.
   */
  public function testServiceMigration() : void {
    // Services has soft dependency on unit migration.
    $this->createUnitMigration();
    $entities = $this->createServiceMigration();
    $this->assertCount(3, $entities);

    foreach (['en', 'sv', 'fi'] as $langcode) {
      foreach ($entities as $entity) {
        $this->assertEquals($entity->getTranslation($langcode)->language()->getId(), $langcode);
      }
    }
  }

}
