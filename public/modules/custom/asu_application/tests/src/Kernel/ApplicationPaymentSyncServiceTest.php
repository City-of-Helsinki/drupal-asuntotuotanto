<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests syncing and reading application payments.
 *
 * @group asu_application
 */
final class ApplicationPaymentSyncServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'asu_application',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('asu_application_payment');
  }

  /**
   * It upserts by unique identity and sorts due dates ascending for UI.
   */
  public function testUpsertIsIdempotentAndListIsSorted(): void {
    $service = $this->container->get('asu_application.payment_sync');

    $summary = $service->upsertPayments([
      [
        'application_id' => 1001,
        'reservation_id' => 'R-1',
        'installment_type' => 'booking_fee',
        'amount' => '100',
        'due_date' => '2026-10-10',
        'account_number' => 'FI0012345600000001',
        'reference_number' => '100100',
        'sent_to_sap' => TRUE,
      ],
      [
        'application_id' => 1001,
        'reservation_id' => 'R-1',
        'installment_type' => 'booking_fee',
        'amount' => '120',
        'due_date' => '2026-10-10',
        'account_number' => 'FI0012345600000001',
        'reference_number' => '100100',
        'sent_to_sap' => TRUE,
      ],
      [
        'application_id' => 1001,
        'reservation_id' => 'R-1',
        'installment_type' => 'final_payment',
        'amount' => '50',
        'due_date' => '2026-09-05',
        'account_number' => 'FI0012345600000001',
        'reference_number' => '100101',
        'sent_to_sap' => TRUE,
      ],
      [
        'application_id' => 1001,
        'reservation_id' => 'R-2',
        'installment_type' => 'ignored_not_sent',
        'amount' => '10',
        'due_date' => '2026-09-01',
        'sent_to_sap' => FALSE,
      ],
    ], 'test-correlation-id');

    $this->assertSame(4, $summary['received']);
    $this->assertSame(2, $summary['created']);
    $this->assertSame(1, $summary['updated']);
    $this->assertSame(1, $summary['skipped']);

    $payments = $service->getPaymentsForApplication(1001);

    $this->assertCount(2, $payments);
    $this->assertSame('final_payment', $payments[0]['installment_type']);
    $this->assertSame('05.09.2026', $payments[0]['due_date']);

    $this->assertSame('booking_fee', $payments[1]['installment_type']);
    $this->assertSame('120,00', $payments[1]['amount']);
    $this->assertSame('10.10.2026', $payments[1]['due_date']);
  }

}
