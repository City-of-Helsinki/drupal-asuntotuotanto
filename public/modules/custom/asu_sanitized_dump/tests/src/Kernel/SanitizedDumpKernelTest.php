<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_sanitized_dump\Kernel;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationMessage;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\asu_project_subscription\Entity\ProjectSubscription;
use Drupal\asu_sanitized_dump\Config\SanitizerConfig;
use Drupal\Core\Database\Database;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use druidfi\GdprDump\MysqldumpGdpr;

/**
 * Kernel tests proving dumps anonymize PII without mutating live rows.
 *
 * @group asu_sanitized_dump
 */
final class SanitizedDumpKernelTest extends KernelTestBase {

  /**
   * Unique PII markers used in assertions.
   */
  private const PII = [
    'name' => 'pii-user-maija-xyz99',
    'mail' => 'pii.maija.xyz99@hel.example',
    'phone' => '+358401112233',
    'saml' => 'pii-saml-hash-xyz99',
    'applicant_email' => 'pii.applicant.xyz99@hel.example',
    'personal_id' => 'Z9Y8X',
    'address' => 'PiiKatu 99 Helsinki',
    'message_body' => 'PII secret message body xyz99',
    'message_mail' => 'pii.message.xyz99@hel.example',
    'subscription_email' => 'pii.subscription.xyz99@hel.example',
    'co_hash' => 'pii-co-applicant-hash-xyz99',
    'confirm_token' => 'pii-confirm-token-xyz99',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'datetime',
    'node',
    'options',
    'language',
    'asu_api',
    'asu_application',
    'asu_project_subscription',
    'asu_sanitized_dump',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (Database::getConnection()->databaseType() !== 'mysql') {
      $this->markTestSkipped('Sanitized dump integration requires MySQL/MariaDB.');
    }

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('application_type');
    $this->installConfig(['node']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('asu_application');
    $this->installEntitySchema('asu_application_message');
    $this->installEntitySchema('asu_project_subscription');

    ApplicationType::create([
      'id' => 'hitas',
      'label' => 'Hitas',
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    if (!NodeType::load('project')) {
      NodeType::create([
        'type' => 'project',
        'name' => 'Project',
      ])->save();
    }

    $this->installUserFields();
    $this->ensureCoApplicantMapTable();
  }

  /**
   * Dump omits original PII while live rows keep it.
   *
   * - Seed user, application, message, subscription, co-applicant map
   * - Run MysqldumpGdpr with ASU expressions against prefixed tables
   * - Dump file must not contain original PII markers
   * - Live database rows must still contain original PII
   * - Dump must still contain INSERT data for users_field_data.
   */
  public function testDumpAnonymizesPiiWithoutMutatingLiveData(): void {
    $user = User::create([
      'name' => self::PII['name'],
      'mail' => self::PII['mail'],
      'status' => 1,
    ]);
    $user->set('field_phone_number', self::PII['phone']);
    $user->set('field_saml_hash', self::PII['saml']);
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    if (!Role::load('customer')) {
      Role::create([
        'id' => 'customer',
        'label' => 'Customer',
      ])->save();
    }
    $user->addRole('customer');
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    $project = Node::create([
      'type' => 'project',
      'title' => 'Dump test project',
      'status' => 1,
    ]);
    $project->save();

    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $user->id(),
      'project_id' => $project->id(),
      'project' => $project->id(),
      'status' => 1,
      'main_applicant' => [
        [
          'first_name' => 'Maija',
          'last_name' => 'Meikalainen',
          'date_of_birth' => '1990-05-05',
          'personal_id' => self::PII['personal_id'],
          'address' => self::PII['address'],
          'postal_code' => '00100',
          'city' => 'Helsinki',
          'phone' => self::PII['phone'],
          'email' => self::PII['applicant_email'],
        ],
      ],
    ]);
    $application->save();

    ApplicationMessage::create([
      'application_id' => (int) $application->id(),
      'project_id' => (int) $project->id(),
      'sender_uid' => (int) $user->id(),
      'sender_role' => 'customer',
      'recipient_mail' => self::PII['message_mail'],
      'body' => self::PII['message_body'],
    ])->save();

    ProjectSubscription::create([
      'project' => $project->id(),
      'email' => self::PII['subscription_email'],
      'uid' => $user->id(),
      'is_confirmed' => TRUE,
      'confirm_token' => self::PII['confirm_token'],
      'unsubscribe_token' => 'pii-unsubscribe-token-xyz99',
    ])->save();

    Database::getConnection()->insert('asu_application_co_applicant_map')
      ->fields([
        'application_id' => (int) $application->id(),
        'co_applicant_saml_hash' => self::PII['co_hash'],
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    $tmp = tempnam(sys_get_temp_dir(), 'asu_sanitized_');
    $this->assertNotFalse($tmp);
    $dump_file = $tmp . '.sql';
    @unlink($tmp);

    $this->runAnonymizedDump($dump_file);
    $dump = file_get_contents($dump_file);
    $this->assertNotFalse($dump);
    @unlink($dump_file);

    foreach (self::PII as $label => $value) {
      $this->assertStringNotContainsString(
        $value,
        $dump,
        "Dump still contains original PII for $label"
      );
    }

    $this->assertStringContainsString('INSERT INTO', $dump);
    $this->assertMatchesRegularExpression('/users_field_data/i', $dump);

    // Live database must be unchanged.
    $live_user = User::load($user->id());
    $this->assertSame(self::PII['name'], $live_user->getAccountName());
    $this->assertSame(self::PII['mail'], $live_user->getEmail());
    $this->assertSame(self::PII['phone'], $live_user->get('field_phone_number')->value);
    $this->assertSame(self::PII['saml'], $live_user->get('field_saml_hash')->value);

    $live_application = Application::load($application->id());
    $main = $live_application->get('main_applicant')->getValue()[0];
    $this->assertSame(self::PII['applicant_email'], $main['email']);
    $this->assertSame(self::PII['personal_id'], $main['personal_id']);
    $this->assertSame(self::PII['address'], $main['address']);

    $message_id = Database::getConnection()->select('asu_application_message', 'm')
      ->fields('m', ['id'])
      ->condition('body', self::PII['message_body'])
      ->execute()
      ->fetchField();
    $this->assertNotFalse($message_id);
    $live_message = ApplicationMessage::load($message_id);
    $this->assertNotNull($live_message);
    $this->assertSame(self::PII['message_body'], $live_message->get('body')->value);
    $this->assertSame(self::PII['message_mail'], $live_message->get('recipient_mail')->value);

    $subscription_email = Database::getConnection()->select('asu_project_subscription', 's')
      ->fields('s', ['email'])
      ->condition('email', self::PII['subscription_email'])
      ->execute()
      ->fetchField();
    $this->assertSame(self::PII['subscription_email'], $subscription_email);

    $co_hash = Database::getConnection()->select('asu_application_co_applicant_map', 'm')
      ->fields('m', ['co_applicant_saml_hash'])
      ->condition('application_id', (int) $application->id())
      ->execute()
      ->fetchField();
    $this->assertSame(self::PII['co_hash'], $co_hash);
  }

  /**
   * Run MysqldumpGdpr for ASU tables using the test DB connection.
   *
   * @param string $dump_file
   *   Destination SQL file path.
   */
  private function runAnonymizedDump(string $dump_file): void {
    $info = Database::getConnectionInfo('default')['default'];
    $host = $info['host'] ?? '127.0.0.1';
    $port = $info['port'] ?? '3306';
    $database = $info['database'];
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $database);
    $prefix = Database::getConnection()->getPrefix();

    $expressions = [];
    foreach (SanitizerConfig::expressions() as $table => $columns) {
      $expressions[$prefix . $table] = $columns;
    }

    $tables = [];
    $schema = Database::getConnection()->schema();
    foreach (array_keys(SanitizerConfig::expressions()) as $table) {
      if ($schema->tableExists($table)) {
        $tables[] = $prefix . $table;
      }
    }
    $this->assertNotEmpty($tables);

    $dump = new MysqldumpGdpr(
      $dsn,
      $info['username'] ?? '',
      $info['password'] ?? '',
      [
        'include-tables' => $tables,
        'add-drop-table' => TRUE,
        'gdpr-expressions' => $expressions,
        'gdpr-replacements' => [],
      ]
    );
    $dump->start($dump_file);
  }

  /**
   * Install user PII field storages used by the dump map.
   */
  private function installUserFields(): void {
    $fields = [
      'field_phone_number' => 'string',
      'field_saml_hash' => 'string',
      'field_full_name' => 'string',
      'field_backend_password' => 'string',
      'field_hel_profiili_uid' => 'string',
    ];
    foreach ($fields as $name => $type) {
      if (!FieldStorageConfig::loadByName('user', $name)) {
        FieldStorageConfig::create([
          'field_name' => $name,
          'entity_type' => 'user',
          'type' => $type,
        ])->save();
      }
      if (!FieldConfig::loadByName('user', 'user', $name)) {
        FieldConfig::create([
          'field_name' => $name,
          'entity_type' => 'user',
          'bundle' => 'user',
          'label' => $name,
        ])->save();
      }
    }
  }

  /**
   * Ensure co-applicant map table exists for dump coverage.
   */
  private function ensureCoApplicantMapTable(): void {
    $schema = Database::getConnection()->schema();
    if ($schema->tableExists('asu_application_co_applicant_map')) {
      return;
    }
    $schema->createTable('asu_application_co_applicant_map', [
      'fields' => [
        'application_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'co_applicant_saml_hash' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'created' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'changed' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => ['application_id'],
    ]);
  }

}
