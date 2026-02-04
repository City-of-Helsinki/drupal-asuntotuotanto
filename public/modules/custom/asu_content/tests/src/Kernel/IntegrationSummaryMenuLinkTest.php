<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Ensures the integration summary menu link is registered.
 *
 * @group asu_content
 */
final class IntegrationSummaryMenuLinkTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    "system",
    "user",
    "asu_content",
  ];

  /**
   * Verifies the integrations summary menu link definition.
   */
  public function testMenuLinkDefinitionExists(): void {
    $menu_link_manager = $this->container->get("plugin.manager.menu.link");
    $definitions = $menu_link_manager->getDefinitions();

    $this->assertArrayHasKey("asu_content.summary_integrations", $definitions);

    $definition = $definitions["asu_content.summary_integrations"];
    $this->assertSame("asu_content.summary_integrations", $definition["route_name"]);
    $this->assertSame("hdbt_admin_tools.overview", $definition["parent"]);
    $this->assertSame("Integration summary", (string) $definition["title"]);
  }

}
