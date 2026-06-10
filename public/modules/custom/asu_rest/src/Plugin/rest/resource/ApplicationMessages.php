<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\asu_application\ApplicationMessageManager;
use Drupal\asu_application\Entity\Application;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides application message thread endpoint.
 *
 * @RestResource(
 *   id = "asu_application_messages",
 *   label = @Translation("Application messages"),
 *   uri_paths = {
 *     "canonical" = "/applications/{application_id}/messages",
 *     "create" = "/applications/{application_id}/messages"
 *   }
 * )
 */
final class ApplicationMessages extends ResourceBase {

  /**
   * Constructs the resource.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    \Psr\Log\LoggerInterface $logger,
    private readonly ApplicationMessageManager $messageManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly RequestStack $requestStack,
    private readonly MailManagerInterface $mailManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('asu_rest'),
      $container->get('asu_application.message_manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('plugin.manager.mail'),
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get(int $application_id): ResourceResponse {
    $application = Application::load($application_id);
    if (!$application) {
      return new ResourceResponse(['message' => 'Application not found.'], 404, $this->getTestingHeaders());
    }

    if (!$this->canReadThread($application)) {
      return new ResourceResponse(['message' => 'Access denied.'], 403, $this->getTestingHeaders());
    }

    $messages = $this->messageManager->loadThread($application_id);
    $items = [];

    foreach ($messages as $message) {
      $items[] = [
        'id' => (int) $message->id(),
        'application_id' => (int) $message->get('application_id')->value,
        'project_id' => (int) $message->get('project_id')->value,
        'sender_role' => (string) $message->get('sender_role')->value,
        'sender_uid' => $message->get('sender_uid')->isEmpty() ? NULL : (int) $message->get('sender_uid')->target_id,
        'salesperson_uid' => $message->get('salesperson_uid')->isEmpty() ? NULL : (int) $message->get('salesperson_uid')->target_id,
        'recipient_mail' => (string) $message->get('recipient_mail')->value,
        'body' => (string) $message->get('body')->value,
        'created' => (int) $message->get('created')->value,
      ];
    }

    $response = new ResourceResponse([
      'application_id' => $application_id,
      'count' => count($items),
      'items' => $items,
    ], 200, $this->getTestingHeaders());

    // This endpoint is used as a live conversation thread and must always
    // return latest messages.
    $cacheability = new CacheableMetadata();
    $cacheability->setCacheMaxAge(0);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * Responds to POST requests.
   */
  public function post(array $data = []): ModifiedResourceResponse {
    $application_id = (int) ($this->requestStack->getCurrentRequest()?->attributes->get('application_id') ?? 0);
    if ($application_id <= 0) {
      return new ModifiedResourceResponse(['message' => 'Application id is missing from request path.'], 400, $this->getTestingHeaders());
    }

    $application = Application::load($application_id);
    if (!$application) {
      return new ModifiedResourceResponse(['message' => 'Application not found.'], 404, $this->getTestingHeaders());
    }

    if (!$this->canWriteThread($application)) {
      return new ModifiedResourceResponse(['message' => 'Access denied.'], 403, $this->getTestingHeaders());
    }

    $body = trim((string) ($data['body'] ?? ''));
    if ($body === '') {
      return new ModifiedResourceResponse(['message' => 'Missing required field: body.'], 400, $this->getTestingHeaders());
    }

    $senderRole = (string) ($data['sender_role'] ?? 'sales');
    if (!in_array($senderRole, ['sales', 'customer'], TRUE)) {
      return new ModifiedResourceResponse(['message' => 'Invalid sender_role. Allowed values: sales, customer.'], 400, $this->getTestingHeaders());
    }

    $message = $this->messageManager->createMessage(
      $application_id,
      $this->messageManager->getProjectId($application),
      $body,
      $senderRole,
      $this->currentUser->isAuthenticated() ? (int) $this->currentUser->id() : NULL,
      NULL,
      (string) ($data['recipient_mail'] ?? ''),
    );

    if ($senderRole === 'sales') {
      $buyerMail = $this->resolveBuyerMail($application);
      if ($buyerMail !== '') {
        $buyerLangcode = $this->resolveBuyerLangcode($application);
        $subject = (string) $this->t(
          'New reply about your application @id',
          ['@id' => (string) $application_id],
          ['langcode' => $buyerLangcode],
        );
        $lines = [
          (string) $this->t('Sales agent replied to your message in the application service.', [], ['langcode' => $buyerLangcode]),
          '',
          (string) $this->t('Application ID: @id', ['@id' => (string) $application_id], ['langcode' => $buyerLangcode]),
          (string) $this->t('Message:', [], ['langcode' => $buyerLangcode]),
          $body,
        ];

        $this->mailManager->mail('asu_application', 'application_message_notification', $buyerMail, $buyerLangcode, [
          'subject' => $subject,
          'message_lines' => $lines,
        ], NULL, TRUE);
      }
    }

    return new ModifiedResourceResponse([
      'message' => 'Message created.',
      'item' => [
        'id' => (int) $message->id(),
        'application_id' => (int) $message->get('application_id')->value,
        'project_id' => (int) $message->get('project_id')->value,
        'sender_role' => (string) $message->get('sender_role')->value,
        'sender_uid' => $message->get('sender_uid')->isEmpty() ? NULL : (int) $message->get('sender_uid')->target_id,
        'salesperson_uid' => $message->get('salesperson_uid')->isEmpty() ? NULL : (int) $message->get('salesperson_uid')->target_id,
        'recipient_mail' => (string) $message->get('recipient_mail')->value,
        'body' => (string) $message->get('body')->value,
        'created' => (int) $message->get('created')->value,
      ],
    ], 200, $this->getTestingHeaders());
  }

  /**
   * Checks read access to message thread.
   */
  private function canReadThread(Application $application): bool {
    return $application->access('view', $this->currentUser, TRUE)->isAllowed()
      || $this->currentUser->hasPermission('restful get asu_application_messages');
  }

  /**
   * Checks write access to message thread.
   */
  private function canWriteThread(Application $application): bool {
    return $application->access('update', $this->currentUser, TRUE)->isAllowed()
      || $this->currentUser->hasPermission('restful post asu_application_messages');
  }

  /**
   * Resolves buyer email address from application owner.
   */
  private function resolveBuyerMail(Application $application): string {
    $owner = $application->getOwner();
    if (!$owner) {
      return '';
    }

    return (string) ($owner->getEmail() ?? '');
  }

  /**
   * Resolves buyer preferred language code.
   */
  private function resolveBuyerLangcode(Application $application): string {
    $owner = $application->getOwner();
    if (!$owner || !method_exists($owner, 'getPreferredLangcode')) {
      return 'fi';
    }

    return $owner->getPreferredLangcode() ?: 'fi';
  }

  /**
   * Add testing headers for local development.
   */
  private function getTestingHeaders(): array {
    return getenv('APP_ENV') === 'testing' ? [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => '*',
      'Access-Control-Allow-Headers' => '*',
    ] : [];
  }

}