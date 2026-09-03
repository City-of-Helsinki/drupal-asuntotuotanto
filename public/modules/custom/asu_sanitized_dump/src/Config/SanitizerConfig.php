<?php

declare(strict_types=1);

namespace Drupal\asu_sanitized_dump\Config;

/**
 * Builds GDPR dump SQL expressions and Faker replacements for ASU PII.
 */
final class SanitizerConfig {

  /**
   * Known local-dev password after importing a sanitized dump.
   */
  public const LOCALDEV_PASSWORD = 'localdev';

  /**
   * Bcrypt hash of LOCALDEV_PASSWORD for users_field_data.pass.
   */
  public const LOCALDEV_PASSWORD_HASH = '$2y$10$NfyVaGxVi9dvgv3O0oyc7e.bN5pCkZH/HSr63rEBqQ1IRAmo8k8Fy';

  /**
   * Applicant multi-column field property names stored in SQL.
   *
   * @var string[]
   */
  private const APPLICANT_PROPERTIES = [
    'first_name',
    'last_name',
    'date_of_birth',
    'personal_id',
    'address',
    'postal_code',
    'city',
    'phone',
    'email',
  ];

  /**
   * Tables whose data should be omitted (structure only) in sanitized dumps.
   *
   * @return string[]
   *   Table name patterns for Drush structure-tables.
   */
  public static function structureTables(): array {
    return [
      'flood',
      'queue',
      'batch',
      'login_history',
      'users_data',
      'authmap',
      'cache',
      'cache_*',
      'history',
      'search_*',
      'sessions',
      'watchdog',
      'feeds_log',
    ];
  }

  /**
   * SQL expressions applied during SELECT for dump anonymization.
   *
   * @return array<string, array<string, string>>
   *   Table → column → SQL expression.
   */
  public static function expressions(): array {
    $hash = "'" . str_replace("'", "''", self::LOCALDEV_PASSWORD_HASH) . "'";

    $expressions = [
      'users_field_data' => [
        'name' => "CASE WHEN uid = 0 THEN name ELSE CONCAT('user', uid) END",
        'mail' => "CASE WHEN uid = 0 THEN mail ELSE CONCAT('user+', uid, '@localhost.localdomain') END",
        'init' => "CASE WHEN uid = 0 THEN init ELSE CONCAT('user+', uid, '@localhost.localdomain') END",
        'pass' => "CASE WHEN uid = 0 THEN pass ELSE $hash END",
      ],
      'asu_application_message' => [
        'recipient_mail' => self::keepEmptyOr(
          'recipient_mail',
          "CONCAT('message+', id, '@example.com')"
        ),
        'body' => self::keepEmptyOr('body', "'Anonymized message'"),
      ],
      'asu_application_co_applicant_map' => [
        'co_applicant_saml_hash' => self::keepEmptyOr(
          'co_applicant_saml_hash',
          "MD5(CONCAT(application_id, '-co-applicant'))"
        ),
      ],
      'asu_project_subscription' => [
        'email' => self::keepEmptyOr(
          'email',
          "CONCAT('subscription+', id, '@example.com')"
        ),
        'confirm_token' => self::keepEmptyOr(
          'confirm_token',
          "MD5(CONCAT(id, '-confirm'))"
        ),
        'unsubscribe_token' => self::keepEmptyOr(
          'unsubscribe_token',
          "MD5(CONCAT(id, '-unsubscribe'))"
        ),
      ],
      'asu_application__field_personal_id' => [
        'field_personal_id_value' => self::keepEmptyOr(
          'field_personal_id_value',
          "'1234A'"
        ),
      ],
      'asu_application_revision__field_personal_id' => [
        'field_personal_id_value' => self::keepEmptyOr(
          'field_personal_id_value',
          "'1234A'"
        ),
      ],
    ];

    foreach (['main_applicant', 'applicant'] as $field_name) {
      foreach (['asu_application', 'asu_application_revision'] as $prefix) {
        $table = $prefix . '__' . $field_name;
        $expressions[$table] = self::applicantFieldExpressions($field_name);
      }
    }

    $user_fields = [
      'field_phone_number' => "CONCAT('+358400', LPAD(entity_id, 6, '0'))",
      'field_full_name' => "CONCAT('User ', entity_id)",
      'field_saml_hash' => "MD5(CONCAT(entity_id, '-saml'))",
      'field_backend_password' => "'anonymized-backend-password'",
      'field_hel_profiili_uid' => "CONCAT('helsinki-profile-', entity_id)",
    ];
    foreach ($user_fields as $field_name => $replacement) {
      $column = $field_name . '_value';
      $expressions['user__' . $field_name] = [
        $column => self::keepEmptyOr($column, $replacement),
      ];
    }

    return $expressions;
  }

  /**
   * Faker replacements (none by default; expressions cover ASU PII).
   *
   * @return array<string, array<string, array{formatter: string}>>
   *   Table → column → Faker formatter config.
   */
  public static function replacements(): array {
    // Expressions handle unique keys and empty passthrough. Returning an empty
    // map clears the module defaults that would otherwise Faker-replace
    // users_field_data.name/mail and break unique indexes.
    return [];
  }

  /**
   * Flat list of table.column keys that must stay configured.
   *
   * @return string[]
   *   Sorted "table.column" identifiers.
   */
  public static function requiredColumns(): array {
    $columns = [];
    foreach (self::expressions() as $table => $fields) {
      foreach (array_keys($fields) as $column) {
        $columns[] = $table . '.' . $column;
      }
    }
    sort($columns);
    return $columns;
  }

  /**
   * Build CASE expression that leaves NULL/empty values unchanged.
   *
   * @param string $column
   *   Column name.
   * @param string $expression
   *   Replacement SQL expression for non-empty values.
   *
   * @return string
   *   Full CASE expression.
   */
  private static function keepEmptyOr(string $column, string $expression): string {
    return "CASE WHEN `$column` IS NULL OR `$column` = '' THEN `$column` ELSE ($expression) END";
  }

  /**
   * Expressions for asu_main_applicant / asu_applicant field tables.
   *
   * @param string $field_name
   *   Field machine name (main_applicant or applicant).
   *
   * @return array<string, string>
   *   Column → expression.
   */
  private static function applicantFieldExpressions(string $field_name): array {
    $map = [
      'first_name' => "CONCAT('First', entity_id)",
      'last_name' => "CONCAT('Last', entity_id)",
      'date_of_birth' => "'1990-01-01'",
      'personal_id' => "'1234A'",
      'address' => "CONCAT(entity_id, ' Example Street')",
      'postal_code' => "'00100'",
      'city' => "'Helsinki'",
      'phone' => "CONCAT('+358401', LPAD(entity_id, 6, '0'))",
      'email' => "CONCAT('applicant+', entity_id, '@example.com')",
    ];

    $expressions = [];
    foreach (self::APPLICANT_PROPERTIES as $property) {
      $column = $field_name . '_' . $property;
      $expressions[$column] = self::keepEmptyOr($column, $map[$property]);
    }
    return $expressions;
  }

}
