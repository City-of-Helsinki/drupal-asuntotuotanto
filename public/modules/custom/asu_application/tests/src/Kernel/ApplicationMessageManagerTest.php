<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\ApplicationMessageManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests application message storage.
 *
 * @group asu_application
 */
final class ApplicationMessageManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'asu_api',
    'asu_application',
  ];

  /**
   * The manager under test.
   *
   * @var \Drupal\asu_application\ApplicationMessageManager
   */
  private ApplicationMessageManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('asu_application_message');

    $this->manager = $this->container->get('asu_application.message_manager');
  }

  /**
   * Tests that messages are scoped to the application and sorted by creation.
   */
  public function testLoadThreadReturnsChronologicalMessages(): void {
    $first = $this->manager->createMessage(1001, 501, 'First message', 'customer');
    $this->manager->createMessage(1002, 501, 'Other application message', 'customer');
    $second = $this->manager->createMessage(1001, 501, 'Second message', 'sales');

    $thread = $this->manager->loadThread(1001);

    $this->assertCount(2, $thread);
    $this->assertSame([(int) $first->id(), (int) $second->id()], array_map(
      static fn ($message): int => (int) $message->id(),
      $thread,
    ));
    $this->assertSame('First message', $thread[0]->get('body')->value);
    $this->assertSame('Second message', $thread[1]->get('body')->value);
  }

}
