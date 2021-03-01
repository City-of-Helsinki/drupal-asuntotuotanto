<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tpr\Functional;

use Drupal\Tests\helfi_api_base\Functional\MigrationTestBase;
use Drupal\Tests\helfi_hauki\Traits\MigrateTrait;

/**
 * Tests entity list functionality.
 *
 * @group helfi_hauki
 */
class ListTest extends MigrationTestBase {

  use MigrateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'helfi_hauki',
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
    $this->drupalGet('/admin/content/hauki-resource');
    $this->assertSession()->statusCodeEquals(403);

    // Make sure logged in user with access remote entities overview permission
    // can see the entity list.
    $account = $this->createUser([
      'access remote entities overview',
      'edit remote entities',
    ]);
    $this->drupalLogin($account);
    // Migrate entities and make sure we can see all entities from fixture.
    $this->createResourceMigration();
    $this->drupalGet('/admin/content/hauki-resource');
    $this->assertSession()->pageTextContains('Displaying 1 - 20 of 20');
    // Make sure we only see english content.
    $this->assertSession()->pageTextContains('Name en 1');
    $this->assertSession()->pageTextNotContains('Name fi');
    $this->assertSession()->pageTextNotContains('Name sv');
    // Make sure we only see finnish content.
    $this->drupalGet('/admin/content/hauki-resource', ['query' => ['language' => 'fi']]);
    $this->assertSession()->pageTextContains('Name fi 1');
    $this->assertSession()->pageTextNotContains('Name en');
    $this->assertSession()->pageTextNotContains('Name sv');
  }

}
