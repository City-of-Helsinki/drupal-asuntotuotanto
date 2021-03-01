<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tpr\Functional;

use Drupal\helfi_tpr\Entity\Unit;
use Drupal\Tests\helfi_api_base\Functional\MigrationTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Tests entity list functionality.
 *
 * @group helfi_tpr
 */
class TprUnitListTest extends MigrationTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'helfi_tpr',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Migrates the tpr unit entities.
   */
  private function runMigrate() : void {
    $units = $this->getFixture('helfi_tpr', 'unit.json');
    $responses = [
      new Response(200, [], $units),
    ];

    foreach (json_decode($units, TRUE) as $id => $unit) {
      $responses[] = new Response(200, [], json_encode($unit));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('tpr_unit');
  }

  /**
   * Tests collection route (views).
   */
  public function testList() : void {
    // Make sure anonymous user can't see the entity list.
    $this->drupalGet('/admin/content/tpr-unit');
    $this->assertSession()->statusCodeEquals(403);

    // Make sure logged in user with access remote entities overview permission
    // can see the entity list.
    $account = $this->createUser([
      'access remote entities overview',
      'edit remote entities',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/content/tpr-unit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('No results found.');

    // Migrate entities and make sure we can see all entities from fixture.
    $this->runMigrate();
    $this->drupalGet('/admin/content/tpr-unit');
    $this->assertSession()->pageTextContains('Displaying 1 - 6 of 6');

    // Make sure we can run 'update' action on multiple entities.
    Unit::load('22736')->set('name', 'Test 1')->save();
    Unit::load('57331')->set('name', 'Test 2')->save();
    $this->drupalGet('/admin/content/tpr-unit');
    $this->assertSession()->pageTextContains('Test 1');
    $this->assertSession()->pageTextContains('Test 2');

    $form_data = [
      'action' => 'tpr_unit_update_action',
      // The list is sorted by changed timestamp so our updated entities
      // should be at the top of the list.
      'tpr_unit_bulk_form[0]' => 1,
      'tpr_unit_bulk_form[1]' => 1,
    ];
    $this->submitForm($form_data, 'Apply to selected items');

    $this->assertSession()->pageTextNotContains('Test 1');
    $this->assertSession()->pageTextNotContains('Test 2');
    $this->assertSession()->pageTextContains('Esteetön testireitti / Leppävaara');
    $this->assertSession()->pageTextContains('InnoOmnia');
  }

}
