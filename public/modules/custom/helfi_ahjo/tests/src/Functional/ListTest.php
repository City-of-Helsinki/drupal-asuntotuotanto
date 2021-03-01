<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_ahjo\Functional;

use Drupal\Tests\helfi_ahjo\Traits\MigrateTrait;
use Drupal\Tests\helfi_api_base\Functional\MigrationTestBase;

/**
 * Tests entity list functionality.
 *
 * @group helfi_ahjo
 */
class ListTest extends MigrationTestBase {

  use MigrateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'helfi_ahjo',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests collection route (views).
   */
  public function testList() : void {
    // Make sure anonymous user can't see the entity list.
    $this->drupalGet('/admin/content/ahjo-issue');
    $this->assertSession()->statusCodeEquals(403);

    // Make sure logged in user with access remote entities overview permission
    // can see the entity list.
    $account = $this->createUser([
      'access remote entities overview',
      'edit remote entities',
    ]);
    $this->drupalLogin($account);
    // Migrate entities and make sure we can see all entities from fixture.
    $this->createIssueMigration();
    $this->drupalGet('/admin/content/ahjo-issue');
    $this->assertSession()->pageTextContains('Displaying 1 - 20 of 40');
    $this->assertSession()->pageTextContains('Name 1');
  }

}
