<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

/**
 * Tests that project attachment URLs are exposed in the REST mapping.
 *
 * Django reads `project_attachment_urls` from the apartment REST payload to
 * build offer materialbank links. The values come from the project link field
 * `field_attachments_url`.
 *
 * @group asu_rest
 */
final class SearchMapperProjectAttachmentUrlsTest extends SearchMapperKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createProjectLinkField(
      'field_attachments_url',
      'Attachments / URL',
      -1,
    );
  }

  /**
   * Maps project attachment URLs when the link field is populated.
   *
   * - Asserts the mapped payload contains `project_attachment_urls`.
   * - Asserts external URLs are returned as absolute strings.
   */
  public function testProjectAttachmentUrlsAreMapped(): void {
    $mapped = $this->mapProject('Project One', [
      'field_attachments_url' => [
        ['uri' => 'https://example.com/mediabank/project'],
        ['uri' => 'https://example.com/mediabank/project-2'],
      ],
    ]);

    $this->assertArrayHasKey('project_attachment_urls', $mapped);
    $this->assertSame(
      [
        'https://example.com/mediabank/project',
        'https://example.com/mediabank/project-2',
      ],
      $mapped['project_attachment_urls']
    );
  }

  /**
   * Maps an empty list when attachment URLs are not set.
   *
   * - Asserts the key is always present so the consumer schema is stable.
   */
  public function testProjectAttachmentUrlsDefaultToEmptyList(): void {
    $mapped = $this->mapProject('Project Without Attachments');

    $this->assertArrayHasKey('project_attachment_urls', $mapped);
    $this->assertSame([], $mapped['project_attachment_urls']);
  }

}
