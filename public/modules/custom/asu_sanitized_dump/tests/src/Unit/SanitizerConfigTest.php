<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_sanitized_dump\Unit;

use Drupal\asu_sanitized_dump\Config\SanitizerConfig;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for SanitizerConfig coverage of ASU PII columns.
 *
 * @group asu_sanitized_dump
 *
 * @coversDefaultClass \Drupal\asu_sanitized_dump\Config\SanitizerConfig
 */
final class SanitizerConfigTest extends UnitTestCase {

  /**
   * Required ASU PII columns must appear in the expression map.
   *
   * - users_field_data identity columns are configured
   * - Applicant columns on the asu_application base table are configured
   * - Subscription and co-applicant hash columns are configured
   * - User field tables for phone/saml/password are configured.
   */
  public function testRequiredColumnsArePresent(): void {
    $columns = SanitizerConfig::requiredColumns();
    $required = [
      'users_field_data.name',
      'users_field_data.mail',
      'users_field_data.pass',
      'users_field_data.init',
      'asu_application.main_applicant__email',
      'asu_application.main_applicant__personal_id',
      'asu_application.main_applicant__first_name',
      'asu_application.applicant__email',
      'asu_application.applicant__personal_id',
      'asu_application_revision.revision_log',
      'asu_application__field_personal_id.field_personal_id_value',
      'asu_application_message.recipient_mail',
      'asu_application_message.body',
      'asu_application_co_applicant_map.co_applicant_saml_hash',
      'asu_application_co_applicant_map.co_applicant_email',
      'asu_project_subscription.email',
      'asu_project_subscription.confirm_token',
      'user__field_phone_number.field_phone_number_value',
      'user__field_saml_hash.field_saml_hash_value',
      'user__field_backend_password.field_backend_password_value',
    ];

    foreach ($required as $column) {
      $this->assertContains($column, $columns, "Missing sanitizer column $column");
    }
  }

  /**
   * Safe field lists load from data/safe_fields.yml.
   *
   * - Every PII entity type has a non-empty list of field names
   * - Linking keys are listed as safe
   * - An unknown entity type has no safe fields.
   */
  public function testSafeFieldsLoad(): void {
    foreach (SanitizerConfig::PII_ENTITY_TYPES as $entity_type) {
      $fields = SanitizerConfig::safeFields($entity_type);
      $this->assertNotEmpty($fields, "No safe fields for $entity_type");
      $this->assertContainsOnly('string', $fields);
    }
    $this->assertContains('field_backend_profile', SanitizerConfig::safeFields('user'));
    $this->assertSame([], SanitizerConfig::safeFields('node'));
  }

  /**
   * Custom table columns must be sanitized or listed as safe.
   *
   * - The current co-applicant map columns are all classified
   * - A new, unknown column is reported
   * - Tables missing from the input (not created yet) are ignored.
   */
  public function testUnclassifiedCustomColumns(): void {
    $columns = [
      'application_id',
      'co_applicant_saml_hash',
      'co_applicant_email',
      'created',
      'changed',
    ];
    $this->assertSame([], SanitizerConfig::unclassifiedCustomColumns([
      'asu_application_co_applicant_map' => $columns,
    ]));

    $this->assertSame(
      ['asu_application_co_applicant_map.co_applicant_phone'],
      SanitizerConfig::unclassifiedCustomColumns([
        'asu_application_co_applicant_map' => [...$columns, 'co_applicant_phone'],
      ])
    );

    $this->assertSame([], SanitizerConfig::unclassifiedCustomColumns([]));
  }

  /**
   * Replacements map stays empty so Faker cannot break unique keys.
   *
   * - replacements() returns an empty array.
   */
  public function testReplacementsAreEmpty(): void {
    $this->assertSame([], SanitizerConfig::replacements());
  }

  /**
   * Unique user columns use SQL expressions, not Faker formatters.
   *
   * - name concatenates uid
   * - mail uses localhost.localdomain pattern
   * - pass uses a generated hash of the localdev password when the env
   *   variable is not set.
   */
  public function testUsersFieldDataUsesSqlExpressions(): void {
    putenv(SanitizerConfig::PASSWORD_HASH_ENV);
    $expressions = SanitizerConfig::expressions()['users_field_data'];
    $this->assertStringContainsString("CONCAT('user', uid)", $expressions['name']);
    $this->assertStringContainsString('@localhost.localdomain', $expressions['mail']);
    $hash = SanitizerConfig::passwordHash();
    $this->assertStringContainsString($hash, $expressions['pass']);
    $this->assertTrue(password_verify(SanitizerConfig::LOCALDEV_PASSWORD, $hash));
    $this->assertSame($hash, SanitizerConfig::passwordHash(), 'The default hash is generated once per run.');
    $this->assertTrue(SanitizerConfig::usesDefaultPassword());
  }

  /**
   * The password hash can be set with an env variable.
   *
   * - A hash in the env variable is used for users_field_data.pass
   * - usesDefaultPassword() reports the override.
   */
  public function testPasswordHashFromEnv(): void {
    $hash = password_hash('env-test-password', PASSWORD_BCRYPT);
    putenv(SanitizerConfig::PASSWORD_HASH_ENV . '=' . $hash);
    try {
      $this->assertSame($hash, SanitizerConfig::passwordHash());
      $this->assertStringContainsString($hash, SanitizerConfig::expressions()['users_field_data']['pass']);
      $this->assertFalse(SanitizerConfig::usesDefaultPassword());
    }
    finally {
      putenv(SanitizerConfig::PASSWORD_HASH_ENV);
    }
  }

  /**
   * A plain password in the env variable is rejected.
   *
   * - passwordHash() throws instead of writing an unusable hash.
   */
  public function testPlainPasswordInEnvIsRejected(): void {
    putenv(SanitizerConfig::PASSWORD_HASH_ENV . '=secret');
    try {
      $this->expectException(\RuntimeException::class);
      SanitizerConfig::passwordHash();
    }
    finally {
      putenv(SanitizerConfig::PASSWORD_HASH_ENV);
    }
  }

  /**
   * Empty-value passthrough CASE wrappers are used for optional PII.
   *
   * - Applicant email expression keeps empty values.
   */
  public function testEmptyValuesArePreservedInExpressions(): void {
    $email = SanitizerConfig::expressions()['asu_application']['main_applicant__email'];
    $this->assertStringContainsString('IS NULL', $email);
    $this->assertStringContainsString("= ''", $email);
  }

  /**
   * Structure-only table list includes auth and session-related data.
   *
   * - authmap, sessions, flood, users_data, login_history are listed
   * - honeypot_user (IP addresses) and key_value_expire (tempstore) are listed.
   */
  public function testStructureTablesIncludeSensitiveSessionData(): void {
    $tables = SanitizerConfig::structureTables();
    foreach (['authmap', 'sessions', 'flood', 'users_data', 'login_history', 'honeypot_user', 'key_value_expire'] as $table) {
      $this->assertContains($table, $tables);
    }
  }

}
