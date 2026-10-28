<?php

declare(strict_types=1);

namespace Drupal\asu_sanitized_dump\Config;

/**
 * Builds GDPR dump SQL expressions and Faker replacements for ASU PII.
 */
final class SanitizerConfig {

  /**
   * Password of every imported user when the env variable is not set.
   */
  public const LOCALDEV_PASSWORD = 'localdev';

  /**
   * Env variable holding the password hash written to users_field_data.pass.
   */
  public const PASSWORD_HASH_ENV = 'ASU_SANITIZED_DUMP_PASSWORD_HASH';

  /**
   * Hash of LOCALDEV_PASSWORD, generated once per run when needed.
   *
   * @var string|null
   */
  private static ?string $defaultPasswordHash = NULL;

  /**
   * Password hash for imported users, from PASSWORD_HASH_ENV or the default.
   *
   * @return string
   *   A Drupal password hash, e.g. from `drush php:eval
   *   "echo \Drupal::service('password')->hash('secret');"`.
   *
   * @throws \RuntimeException
   *   When the env value does not look like a password hash.
   */
  public static function passwordHash(): string {
    $hash = getenv(self::PASSWORD_HASH_ENV);
    if ($hash === FALSE || $hash === '') {
      // Generated rather than stored, so no password hash lives in the code.
      // Drupal's password service verifies bcrypt hashes with
      // password_verify().
      return self::$defaultPasswordHash ??= password_hash(self::LOCALDEV_PASSWORD, PASSWORD_BCRYPT);
    }
    // Drupal hashes start with "$" ($2y$ bcrypt, $S$ phpass). A plain password
    // here would lock every imported user out.
    if (!str_starts_with($hash, '$')) {
      throw new \RuntimeException(sprintf('%s must be a password hash, not a plain password.', self::PASSWORD_HASH_ENV));
    }
    return $hash;
  }

  /**
   * Whether imported users get the default LOCALDEV_PASSWORD.
   */
  public static function usesDefaultPassword(): bool {
    return in_array(getenv(self::PASSWORD_HASH_ENV), [FALSE, ''], TRUE);
  }

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
   * Entity types whose stored data can contain personal data.
   *
   * Every column of their fields must be sanitized in expressions() or the
   * field listed in SAFE_FIELDS; SanitizerCoverageKernelTest enforces this.
   */
  public const PII_ENTITY_TYPES = [
    'user',
    'asu_application',
    'asu_application_message',
    'asu_project_subscription',
  ];

  /**
   * Fields of PII entity types that are safe to dump unchanged.
   *
   * Ids, references, flags, timestamps and fixed-vocabulary values. Keys
   * linking to the backend (users.uuid, field_backend_profile,
   * field_backend_id) must be kept for the restored data to work.
   */
  public const SAFE_FIELDS = [
    'user' => [
      'uid',
      'uuid',
      'langcode',
      'preferred_langcode',
      'preferred_admin_langcode',
      'timezone',
      'status',
      'created',
      'changed',
      'access',
      'login',
      'default_langcode',
      'roles',
      'field_backend_profile',
      'field_email_is_valid',
    ],
    'asu_application' => [
      'id',
      'vid',
      'bundle',
      'uuid',
      'status',
      'uid',
      'parent_id',
      'project_id',
      'project',
      'field_backend_id',
      'has_children',
      'created_admin',
      'created_by',
      'create_to_django',
      'created',
      'changed',
      'field_locked',
      // Fixed error messages looked up by backend error code.
      'error',
      'revision_timestamp',
      'revision_uid',
      'revision_default',
      'apartment',
      'field_agreement_policy',
      'field_aso_changer',
      'field_data_agreement_policy',
      'field_hitas_owner',
      'field_is_new_permit_number',
      // Kept on purpose: drives HASO queue ordering (also kept in Django).
      'field_right_of_residence_number',
    ],
    'asu_application_message' => [
      'id',
      'uuid',
      'application_id',
      'project_id',
      'sender_uid',
      'salesperson_uid',
      'sender_role',
      'created',
      'changed',
    ],
    'asu_project_subscription' => [
      'id',
      'uuid',
      'langcode',
      'project',
      'uid',
      'is_confirmed',
      'unsubscribed_at',
      'last_notified_state',
      'created',
      'changed',
    ],
  ];

  /**
   * Custom (non-entity) tables that can hold personal data.
   *
   * Table → columns that are safe to dump unchanged. Every other column must
   * have an expression; SanitizedDumpManager refuses to dump otherwise.
   */
  public const CUSTOM_TABLES = [
    'asu_application_co_applicant_map' => [
      'application_id',
      'created',
      'changed',
    ],
  ];

  /**
   * Columns of existing custom tables that are neither sanitized nor safe.
   *
   * @param array<string, string[]> $table_columns
   *   Actual columns per custom table; tables that do not exist are omitted.
   *
   * @return string[]
   *   "table.column" identifiers.
   */
  public static function unclassifiedCustomColumns(array $table_columns): array {
    $expressions = self::expressions();
    $unclassified = [];
    foreach ($table_columns as $table => $columns) {
      $safe = self::CUSTOM_TABLES[$table] ?? [];
      foreach ($columns as $column) {
        if (!in_array($column, $safe, TRUE) && !isset($expressions[$table][$column])) {
          $unclassified[] = "$table.$column";
        }
      }
    }
    return $unclassified;
  }

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
      // Visitor IP addresses.
      'honeypot_user',
      // Private tempstore and other expiring state, e.g. draft form values.
      'key_value_expire',
    ];
  }

  /**
   * SQL expressions applied during SELECT for dump anonymization.
   *
   * @return array<string, array<string, string>>
   *   Table → column → SQL expression.
   */
  public static function expressions(): array {
    $hash = "'" . str_replace("'", "''", self::passwordHash()) . "'";

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
        'co_applicant_email' => self::keepEmptyOr(
          'co_applicant_email',
          "CONCAT('co-applicant+', application_id, '@example.com')"
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

    // main_applicant and applicant are single-value base fields, stored as
    // columns of the asu_application base table (not revisionable).
    $expressions['asu_application'] = array_merge(
      self::applicantFieldExpressions('main_applicant'),
      self::applicantFieldExpressions('applicant'),
    );
    $expressions['asu_application_revision'] = [
      'revision_log' => self::keepEmptyOr('revision_log', "'Anonymized revision log'"),
    ];

    // field_backend_profile and field_backend_password are masked with the
    // source backend's HASHIDS_SALT. After restoring, the backend command
    // `drupal_restore_credentials_sql` recomputes both for the target
    // environment; until then the password placeholder fails login on purpose.
    $user_fields = [
      'field_phone_number' => "CONCAT('+358400', LPAD(entity_id, 6, '0'))",
      'field_full_name' => "CONCAT('User ', entity_id)",
      'field_saml_hash' => "MD5(CONCAT(entity_id, '-saml'))",
      'field_backend_password' => "'run-drupal-restore-credentials-sql'",
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
    // Expressions handle unique keys and empty passthrough; Faker values can
    // collide and break unique indexes such as users_field_data.name/mail.
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
   * Expressions for main_applicant / applicant columns on asu_application.
   *
   * @param string $field_name
   *   Field machine name (main_applicant or applicant).
   *
   * @return array<string, string>
   *   Column → expression.
   */
  private static function applicantFieldExpressions(string $field_name): array {
    $map = [
      'first_name' => "CONCAT('First', id)",
      'last_name' => "CONCAT('Last', id)",
      'date_of_birth' => "'1990-01-01'",
      'personal_id' => "'1234A'",
      'address' => "CONCAT(id, ' Example Street')",
      'postal_code' => "'00100'",
      'city' => "'Helsinki'",
      'phone' => "CONCAT('+358401', LPAD(id, 6, '0'))",
      'email' => "CONCAT('$field_name+', id, '@example.com')",
    ];

    $expressions = [];
    foreach (self::APPLICANT_PROPERTIES as $property) {
      $column = $field_name . '__' . $property;
      $expressions[$column] = self::keepEmptyOr($column, $map[$property]);
    }
    return $expressions;
  }

}
