<?php

declare(strict_types=1);

namespace Drupal\asu_application;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationMessage;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Handles application message persistence.
 */
final class ApplicationMessageManager {

  /**
   * User data key for storing per-application last-read timestamps.
   */
  private const LAST_READ_USER_DATA_KEY = 'message_thread_last_read';

  /**
   * State key for storing shared sales last-read timestamps.
   */
  private const LAST_READ_SALES_SHARED_STATE_KEY = 'asu_application.message_thread_last_read_sales_shared';

  /**
   * Constructs the manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly UserDataInterface $userData,
    private readonly StateInterface $state,
  ) {
  }

  /**
   * Creates and stores a new application message.
   */
  public function createMessage(
    int $applicationId,
    int $projectId,
    string $body,
    string $senderRole,
    ?int $senderUid = NULL,
    ?int $salespersonUid = NULL,
    string $recipientMail = '',
  ): ApplicationMessage {
    /** @var \Drupal\asu_application\Entity\ApplicationMessage $message */
    $message = $this->entityTypeManager->getStorage('asu_application_message')->create([
      'application_id' => $applicationId,
      'project_id' => $projectId,
      'body' => $body,
      'sender_role' => $senderRole,
      'sender_uid' => $senderUid,
      'salesperson_uid' => $salespersonUid,
      'recipient_mail' => $recipientMail,
    ]);
    $message->save();
    $this->invalidateUnreadTagsForMessage($applicationId, $senderRole, $senderUid, $salespersonUid);

    return $message;
  }

  /**
   * Loads an application thread in chronological order.
   *
   * @return \Drupal\asu_application\Entity\ApplicationMessage[]
   *   Messages for the given application.
   */
  public function loadThread(int $applicationId): array {
    $ids = $this->entityTypeManager->getStorage('asu_application_message')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('application_id', $applicationId)
      ->sort('created', 'ASC')
      ->sort('id', 'ASC')
      ->execute();

    if ($ids === []) {
      return [];
    }

    /** @var \Drupal\asu_application\Entity\ApplicationMessage[] $messages */
    $messages = $this->entityTypeManager->getStorage('asu_application_message')->loadMultiple($ids);
    return array_values($messages);
  }

  /**
   * Resolves the salesperson assigned to the message application project.
   */
  public function resolveSalesperson(Application $application): ?UserInterface {
    $project = $this->loadProject($application);
    if (!$project) {
      return NULL;
    }

    if (method_exists($project, 'getSalesPerson')) {
      $salesperson = $project->getSalesPerson();
      if ($salesperson instanceof UserInterface) {
        return $salesperson;
      }
    }

    if ($project->hasField('field_salesperson') && !$project->get('field_salesperson')->isEmpty()) {
      $salesperson = $project->get('field_salesperson')->entity;
      if ($salesperson instanceof UserInterface) {
        return $salesperson;
      }
    }

    return NULL;
  }

  /**
   * Resolves the notification recipient email for the application project.
   */
  public function resolveRecipientMail(Application $application): string {
    $salesperson = $this->resolveSalesperson($application);
    if ($salesperson && $salesperson->getEmail()) {
      return $salesperson->getEmail();
    }

    return (string) (getenv('DRUPAL_DEFAULT_FORM_EMAIL') ?: '');
  }

  /**
   * Resolves customer recipients for salesperson notifications.
   *
   * Includes application owner and mapped co-applicant accounts.
   *
   * @return array<int, array{uid:int, mail:string, langcode:string}>
   *   Unique recipients by email.
   */
  public function resolveCustomerRecipients(Application $application): array {
    $recipients = [];

    $owner = $application->getOwner();
    if ($owner && $owner->getEmail()) {
      $recipients[] = [
        'uid' => (int) $owner->id(),
        'mail' => (string) $owner->getEmail(),
        'langcode' => method_exists($owner, 'getPreferredLangcode')
          ? ($owner->getPreferredLangcode() ?: 'fi')
          : 'fi',
      ];
    }

    $schema = \Drupal::database()->schema();
    if (!$schema->tableExists('asu_application_co_applicant_map')) {
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $applicationId = (int) $application->id();
    if ($applicationId <= 0) {
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $hasCoApplicantEmailColumn = $schema->fieldExists('asu_application_co_applicant_map', 'co_applicant_email');
    $mapQuery = \Drupal::database()
      ->select('asu_application_co_applicant_map', 'm')
      ->fields('m', ['co_applicant_saml_hash']);

    if ($hasCoApplicantEmailColumn) {
      $mapQuery->fields('m', ['co_applicant_email']);
    }

    $mappingRow = $mapQuery
      ->condition('application_id', $applicationId)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($mappingRow) || $mappingRow === []) {
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $coApplicantEmail = trim((string) ($mappingRow['co_applicant_email'] ?? ''));
    if ($hasCoApplicantEmailColumn) {
      if (filter_var($coApplicantEmail, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = [
          'uid' => 0,
          'mail' => $coApplicantEmail,
          'langcode' => 'fi',
        ];
      }

      // When explicit co-applicant email storage is available, avoid
      // hash-based lookup that may match an unrelated local test account.
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $samlHash = $mappingRow['co_applicant_saml_hash'] ?? '';

    if (!is_string($samlHash) || $samlHash === '') {
      return $this->uniqueRecipientsByEmail($recipients);
    }

    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['field_saml_hash' => $samlHash]);

    foreach ($users as $user) {
      if (!$user instanceof UserInterface || !$user->getEmail()) {
        continue;
      }

      $recipients[] = [
        'uid' => (int) $user->id(),
        'mail' => (string) $user->getEmail(),
        'langcode' => method_exists($user, 'getPreferredLangcode')
          ? ($user->getPreferredLangcode() ?: 'fi')
          : 'fi',
      ];
    }

    return $this->uniqueRecipientsByEmail($recipients);
  }

  /**
   * Resolves chat viewer role from user account type.
   */
  public function resolveViewerRole(int $uid): string {
    if ($uid <= 0) {
      return 'customer';
    }

    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface) {
      return 'customer';
    }

    if (method_exists($account, 'bundle') && $account->bundle() === 'sales') {
      return 'sales';
    }

    return in_array('sales', $account->getRoles(), TRUE) ? 'sales' : 'customer';
  }

  /**
   * Returns thread last-read timestamp for a user.
   */
  public function getThreadLastRead(int $uid, int $applicationId): int {
    if ($uid <= 0 || $applicationId <= 0) {
      return 0;
    }

    if ($this->resolveViewerRole($uid) === 'sales') {
      $map = $this->getSharedSalesLastReadMap();
      return (int) ($map[$applicationId] ?? 0);
    }

    $map = $this->getLastReadMap($uid);
    return (int) ($map[$applicationId] ?? 0);
  }

  /**
   * Marks thread as read for a user.
   */
  public function markThreadRead(int $uid, int $applicationId, ?int $timestamp = NULL): void {
    if ($uid <= 0 || $applicationId <= 0) {
      return;
    }

    $timestamp ??= \Drupal::time()->getRequestTime();

    if ($this->resolveViewerRole($uid) === 'sales') {
      $this->markThreadReadSalesShared($applicationId, $timestamp);

      return;
    }

    $map = $this->getLastReadMap($uid);
    $current = (int) ($map[$applicationId] ?? 0);

    if ($timestamp > $current) {
      $map[$applicationId] = $timestamp;
      $this->userData->set('asu_application', $uid, self::LAST_READ_USER_DATA_KEY, $map);
      Cache::invalidateTags([$this->getUnreadCacheTag($uid, $applicationId)]);
    }
  }

  /**
   * Marks thread as read for shared sales context.
   */
  public function markThreadReadSalesShared(int $applicationId, ?int $timestamp = NULL): void {
    if ($applicationId <= 0) {
      return;
    }

    $timestamp ??= \Drupal::time()->getRequestTime();

    $map = $this->getSharedSalesLastReadMap();
    $current = (int) ($map[$applicationId] ?? 0);

    if ($timestamp > $current) {
      $map[$applicationId] = $timestamp;
      $this->state->set(self::LAST_READ_SALES_SHARED_STATE_KEY, $map);
      Cache::invalidateTags([$this->getSharedSalesUnreadCacheTag($applicationId)]);
    }
  }

  /**
   * Returns cache tag for unread state of a user/application pair.
   */
  public function getUnreadCacheTag(int $uid, int $applicationId): string {
    return sprintf('asu_application_unread:%d:%d', $uid, $applicationId);
  }

  /**
   * Returns cache tag for shared sales unread state of an application.
   */
  public function getSharedSalesUnreadCacheTag(int $applicationId): string {
    return sprintf('asu_application_unread:sales:%d', $applicationId);
  }

  /**
   * Returns unread message counts grouped by application.
   *
   * @param int $uid
   *   Current user id.
   * @param int[] $applicationIds
   *   Application ids to calculate counts for.
   * @param string|null $viewerRole
   *   Optional explicit role side ('customer' or 'sales').
   *
   * @return array<int, int>
   *   Map of application id => unread count.
   */
  public function getUnreadCountsForApplications(int $uid, array $applicationIds, ?string $viewerRole = NULL): array {
    $counts = [];

    $viewerRole = $viewerRole ?: $this->resolveViewerRole($uid);
    if ($uid <= 0 && $viewerRole !== 'sales') {
      return $counts;
    }

    $applicationIds = array_values(array_unique(array_filter(array_map('intval', $applicationIds))));
    if ($applicationIds === []) {
      return $counts;
    }

    foreach ($applicationIds as $applicationId) {
      $counts[$applicationId] = 0;
    }

    $incomingSenderRole = $viewerRole === 'sales' ? 'customer' : 'sales';
    $lastRead = $viewerRole === 'sales'
      ? $this->getSharedSalesLastReadMap()
      : $this->getLastReadMap($uid);

    $rows = \Drupal::database()
      ->select('asu_application_message', 'm')
      ->fields('m', ['application_id', 'created'])
      ->condition('m.application_id', $applicationIds, 'IN')
      ->condition('m.sender_role', $incomingSenderRole)
      ->execute();

    foreach ($rows as $row) {
      $applicationId = (int) $row->application_id;
      $created = (int) $row->created;
      $readAt = (int) ($lastRead[$applicationId] ?? 0);

      if ($created > $readAt) {
        $counts[$applicationId]++;
      }
    }

    return $counts;
  }

  /**
   * Returns sales inbox summary per application.
   *
   * Includes only applications that have customer messages.
   *
   * @param int[] $applicationIds
   *   Optional application ids to limit summary generation.
   *
   * @return array<int, array{application_id:int, unread_count:int, last_message_at:?string, last_message_preview:string, has_unread:bool}>
   *   Summary rows keyed numerically.
   */
  public function getSalesInboxSummary(array $applicationIds = []): array {
    $applicationIds = array_values(array_unique(array_filter(array_map('intval', $applicationIds), static fn(int $id): bool => $id > 0)));

    $latestRows = \Drupal::database()
      ->select('asu_application_message', 'm')
      ->fields('m', ['application_id', 'body', 'created', 'id'])
      ->condition('m.sender_role', 'customer')
      ->orderBy('m.application_id', 'ASC')
      ->orderBy('m.created', 'DESC')
      ->orderBy('m.id', 'DESC');

    if ($applicationIds !== []) {
      $latestRows->condition('m.application_id', $applicationIds, 'IN');
    }

    $latestRows = $latestRows->execute();

    $latestByApplication = [];
    foreach ($latestRows as $row) {
      $applicationId = (int) $row->application_id;
      if ($applicationId <= 0 || isset($latestByApplication[$applicationId])) {
        continue;
      }

      $latestByApplication[$applicationId] = [
        'created' => (int) $row->created,
        'body' => (string) ($row->body ?? ''),
      ];
    }

    if ($latestByApplication === []) {
      return [];
    }

    $applicationIds = array_keys($latestByApplication);
    sort($applicationIds);
    $unreadCounts = $this->getUnreadCountsForApplications(0, $applicationIds, 'sales');

    $items = [];
    foreach ($applicationIds as $applicationId) {
      $latest = $latestByApplication[$applicationId];
      $created = (int) ($latest['created'] ?? 0);
      $preview = trim((string) ($latest['body'] ?? ''));
      $preview = mb_substr($preview, 0, 200);
      $unreadCount = (int) ($unreadCounts[$applicationId] ?? 0);

      $items[] = [
        'application_id' => $applicationId,
        'unread_count' => $unreadCount,
        'last_message_at' => $created > 0 ? gmdate('c', $created) : NULL,
        'last_message_preview' => $preview,
        'has_unread' => $unreadCount > 0,
      ];
    }

    return $items;
  }

  /**
   * Returns the project id for the application.
   */
  public function getProjectId(Application $application): int {
    if ($application->hasField('project') && !$application->get('project')->isEmpty() && $application->get('project')->entity) {
      return (int) $application->get('project')->entity->id();
    }

    return (int) ($application->get('project_id')->value ?? 0);
  }

  /**
   * Returns the application project label.
   */
  public function getProjectLabel(Application $application): string {
    $project = $this->loadProject($application);
    return $project ? (string) $project->label() : '';
  }

  /**
   * Loads the application project entity.
   */
  private function loadProject(Application $application): ?object {
    if ($application->hasField('project') && !$application->get('project')->isEmpty() && $application->get('project')->entity) {
      return $application->get('project')->entity;
    }

    $projectId = (int) ($application->get('project_id')->value ?? 0);
    if ($projectId <= 0) {
      return NULL;
    }

    return $this->entityTypeManager->getStorage('node')->load($projectId);
  }

  /**
   * Removes duplicate recipients by normalized email.
   *
   * @param array<int, array{uid:int, mail:string, langcode:string}> $recipients
   *   Raw recipients.
   *
   * @return array<int, array{uid:int, mail:string, langcode:string}>
   *   Deduplicated recipients.
   */
  private function uniqueRecipientsByEmail(array $recipients): array {
    $unique = [];
    $seen = [];

    foreach ($recipients as $recipient) {
      $mail = mb_strtolower(trim($recipient['mail']));
      if ($mail === '' || isset($seen[$mail])) {
        continue;
      }

      $seen[$mail] = TRUE;
      $recipient['mail'] = $mail;
      $unique[] = $recipient;
    }

    return $unique;
  }

  /**
   * Returns stored last-read map for the user.
   *
   * @return array<int, int>
   *   Map of application id => timestamp.
   */
  private function getLastReadMap(int $uid): array {
    $raw = $this->userData->get('asu_application', $uid, self::LAST_READ_USER_DATA_KEY);
    if (!is_array($raw)) {
      return [];
    }

    $map = [];
    foreach ($raw as $applicationId => $timestamp) {
      $appId = (int) $applicationId;
      $ts = (int) $timestamp;
      if ($appId > 0 && $ts >= 0) {
        $map[$appId] = $ts;
      }
    }

    return $map;
  }

  /**
   * Returns shared sales last-read map.
   *
   * @return array<int, int>
   *   Map of application id => timestamp.
   */
  private function getSharedSalesLastReadMap(): array {
    $raw = $this->state->get(self::LAST_READ_SALES_SHARED_STATE_KEY, []);
    if (!is_array($raw)) {
      return [];
    }

    $map = [];
    foreach ($raw as $applicationId => $timestamp) {
      $appId = (int) $applicationId;
      $ts = (int) $timestamp;
      if ($appId > 0 && $ts >= 0) {
        $map[$appId] = $ts;
      }
    }

    return $map;
  }

  /**
   * Invalidates unread cache tags affected by a newly created message.
   */
  private function invalidateUnreadTagsForMessage(
    int $applicationId,
    string $senderRole,
    ?int $senderUid,
    ?int $salespersonUid = NULL,
  ): void {
    if ($applicationId <= 0) {
      return;
    }

    $targetUids = [];

    if ($senderRole === 'sales') {
      try {
        $application = $this->entityTypeManager->getStorage('asu_application')->load($applicationId);
        if ($application instanceof Application) {
          foreach ($this->resolveCustomerRecipients($application) as $recipient) {
            $uid = (int) ($recipient['uid'] ?? 0);
            if ($uid > 0) {
              $targetUids[] = $uid;
            }
          }
        }
      }
      catch (\Throwable) {
        // Skip recipient-based cache invalidation when related entity storages
        // are unavailable in lightweight test environments.
      }
    }
    else {
      if ($salespersonUid && $salespersonUid > 0) {
        $targetUids[] = (int) $salespersonUid;
      }

      Cache::invalidateTags([
        $this->getSharedSalesUnreadCacheTag($applicationId),
      ]);
    }

    if ($targetUids === []) {
      return;
    }

    $targetUids = array_values(array_unique($targetUids));
    $tags = [];
    foreach ($targetUids as $uid) {
      $tags[] = $this->getUnreadCacheTag($uid, $applicationId);
    }

    Cache::invalidateTags($tags);
  }

}
