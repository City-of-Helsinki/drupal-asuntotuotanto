<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

/**
 * Tests that project virtual presentation URLs are exposed in the REST mapping.
 *
 * Django reads `project_virtual_presentation_url` for the Oikotie
 * VirtualPresentation element, which must be an http(s) URL or omitted.
 *
 * @group asu_rest
 */
final class SearchMapperVirtualPresentationUrlTest extends SearchMapperKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createProjectLinkField(
      'field_virtual_presentation_url',
      'Virtual presentation URL',
    );
  }

  /**
   * Maps the project virtual presentation URL when the link field is set.
   *
   * - Asserts the mapped payload contains `project_virtual_presentation_url`.
   * - Asserts an external URI is returned as an absolute string.
   */
  public function testProjectVirtualPresentationUrlIsMapped(): void {
    $mapped = $this->mapProject('Project With Tour', [
      'field_virtual_presentation_url' => [
        ['uri' => 'https://example.com/virtual-tour'],
      ],
    ]);

    $this->assertArrayHasKey('project_virtual_presentation_url', $mapped);
    $this->assertSame(
      'https://example.com/virtual-tour',
      $mapped['project_virtual_presentation_url'],
    );
  }

  /**
   * Maps an empty string when the virtual presentation URL is not set.
   *
   * - Asserts the key is always present so the consumer schema is stable.
   */
  public function testProjectVirtualPresentationUrlDefaultsToEmptyString(): void {
    $mapped = $this->mapProject('Project Without Tour');

    $this->assertArrayHasKey('project_virtual_presentation_url', $mapped);
    $this->assertSame('', $mapped['project_virtual_presentation_url']);
  }

}
