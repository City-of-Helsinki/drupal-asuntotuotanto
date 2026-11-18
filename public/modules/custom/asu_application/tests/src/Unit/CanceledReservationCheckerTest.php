<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Service\CanceledReservationChecker;
use Drupal\asu_content\Entity\Project;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests detection of applications whose Django reservations are all canceled.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Service\CanceledReservationChecker
 */
final class CanceledReservationCheckerTest extends UnitTestCase {

  private const OWNER_ID = 7;
  private const PROJECT_ID = 42;

  /**
   * Reservation lists and whether they count as all canceled.
   *
   * @return array
   *   Test cases: [reservations, expected].
   */
  public static function reservationsProvider(): array {
    return [
      'single canceled reservation' => [[['state' => 'canceled']], TRUE],
      'every reservation canceled' => [
        [['state' => 'canceled'], ['state' => 'canceled']],
        TRUE,
      ],
      'one reservation still active' => [
        [['state' => 'canceled'], ['state' => 'submitted']],
        FALSE,
      ],
      'offered reservation' => [[['state' => 'offered']], FALSE],
      // No reservations: Django has not processed the application yet, or
      // the request failed. Keep treating the application as active.
      'no reservations' => [[], FALSE],
      'reservation without state' => [[['id' => 1]], FALSE],
    ];
  }

  /**
   * Only a non-empty list of canceled reservations is all canceled.
   *
   * @dataProvider reservationsProvider
   * @covers ::areAllCanceled
   */
  public function testAreAllCanceled(array $reservations, bool $expected): void {
    $this->assertSame($expected, CanceledReservationChecker::areAllCanceled($reservations));
  }

  /**
   * A sent application with only canceled reservations is canceled.
   *
   * @covers ::isApplicationCanceled
   */
  public function testSentApplicationWithCanceledReservationsIsCanceled(): void {
    $project = $this->project([['state' => 'canceled']]);
    $checker = $this->checker($project, self::OWNER_ID);

    $this->assertTrue($checker->isApplicationCanceled($this->application('backend-uuid')));
  }

  /**
   * An application with an active reservation is not canceled.
   *
   * @covers ::isApplicationCanceled
   */
  public function testApplicationWithActiveReservationIsNotCanceled(): void {
    $project = $this->project([['state' => 'submitted']]);
    $checker = $this->checker($project, self::OWNER_ID);

    $this->assertFalse($checker->isApplicationCanceled($this->application('backend-uuid')));
  }

  /**
   * A draft (never sent to Django) is not canceled and Django is not asked.
   *
   * @covers ::isApplicationCanceled
   */
  public function testDraftIsNotCanceled(): void {
    $project = $this->createMock(Project::class);
    $project->expects($this->never())->method('getUserReservations');
    $checker = $this->checker($project, self::OWNER_ID);

    $this->assertFalse($checker->isApplicationCanceled($this->application(NULL)));
  }

  /**
   * Another user's application is not checked.
   *
   * Django returns the reservations of the current user only, so they say
   * nothing about an application a salesperson views for a customer.
   *
   * @covers ::isApplicationCanceled
   */
  public function testOtherUsersApplicationIsNotChecked(): void {
    $project = $this->createMock(Project::class);
    $project->expects($this->never())->method('getUserReservations');
    $checker = $this->checker($project, self::OWNER_ID + 1);

    $this->assertFalse($checker->isApplicationCanceled($this->application('backend-uuid')));
  }

  /**
   * Django is asked once per project within a request.
   *
   * @covers ::isApplicationCanceled
   */
  public function testReservationsAreFetchedOncePerProject(): void {
    $project = $this->createMock(Project::class);
    $project->method('id')->willReturn(self::PROJECT_ID);
    $project->expects($this->once())
      ->method('getUserReservations')
      ->willReturn([['state' => 'canceled']]);
    $checker = $this->checker($project, self::OWNER_ID);

    $checker->isApplicationCanceled($this->application('backend-uuid'));
    $this->assertTrue($checker->isApplicationCanceled($this->application('backend-uuid')));
  }

  /**
   * Build a project mock returning the given reservations.
   *
   * @param array $reservations
   *   Reservations returned by Django.
   *
   * @return \Drupal\asu_content\Entity\Project
   *   The project mock.
   */
  private function project(array $reservations): Project {
    $project = $this->createMock(Project::class);
    $project->method('id')->willReturn(self::PROJECT_ID);
    $project->method('getUserReservations')->willReturn($reservations);
    return $project;
  }

  /**
   * Build an application mock owned by OWNER_ID in PROJECT_ID.
   *
   * @param string|null $backendId
   *   Django application uuid, or NULL for a draft.
   *
   * @return \Drupal\asu_application\Entity\Application
   *   The application mock.
   */
  private function application(?string $backendId): Application {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getString')->willReturn($backendId ?? '');

    $application = $this->createMock(Application::class);
    $application->method('getOwnerId')->willReturn(self::OWNER_ID);
    $application->method('getProjectId')->willReturn((string) self::PROJECT_ID);
    $application->method('get')->with('field_backend_id')->willReturn($field);
    return $application;
  }

  /**
   * Build the checker with the given project and current user.
   *
   * @param \Drupal\asu_content\Entity\Project $project
   *   Project loaded for any id.
   * @param int $currentUserId
   *   Id of the current user.
   *
   * @return \Drupal\asu_application\Service\CanceledReservationChecker
   *   The checker.
   */
  private function checker(Project $project, int $currentUserId): CanceledReservationChecker {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($project);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn($currentUserId);

    return new CanceledReservationChecker($entityTypeManager, $currentUser);
  }

}
