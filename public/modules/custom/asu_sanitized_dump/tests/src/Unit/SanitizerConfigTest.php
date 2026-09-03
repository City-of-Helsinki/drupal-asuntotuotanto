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
   * - Applicant dedicated field tables include personal_id and email
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
      'asu_application__main_applicant.main_applicant_email',
      'asu_application__main_applicant.main_applicant_personal_id',
      'asu_application__applicant.applicant_email',
      'asu_application_revision__main_applicant.main_applicant_first_name',
      'asu_application__field_personal_id.field_personal_id_value',
      'asu_application_message.recipient_mail',
      'asu_application_message.body',
      'asu_application_co_applicant_map.co_applicant_saml_hash',
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
   * - pass uses the localdev hash constant.
   */
  public function testUsersFieldDataUsesSqlExpressions(): void {
    $expressions = SanitizerConfig::expressions()['users_field_data'];
    $this->assertStringContainsString("CONCAT('user', uid)", $expressions['name']);
    $this->assertStringContainsString('@localhost.localdomain', $expressions['mail']);
    $this->assertStringContainsString(SanitizerConfig::LOCALDEV_PASSWORD_HASH, $expressions['pass']);
  }

  /**
   * Empty-value passthrough CASE wrappers are used for optional PII.
   *
   * - Applicant email expression keeps empty values.
   */
  public function testEmptyValuesArePreservedInExpressions(): void {
    $email = SanitizerConfig::expressions()['asu_application__main_applicant']['main_applicant_email'];
    $this->assertStringContainsString('IS NULL', $email);
    $this->assertStringContainsString("= ''", $email);
  }

  /**
   * Structure-only table list includes auth and session-related data.
   *
   * - authmap, sessions, flood, users_data are listed.
   */
  public function testStructureTablesIncludeSensitiveSessionData(): void {
    $tables = SanitizerConfig::structureTables();
    foreach (['authmap', 'sessions', 'flood', 'users_data', 'login_history'] as $table) {
      $this->assertContains($table, $tables);
    }
  }

}
