<?php

namespace Drupal\asu_user\EventSubscriber;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\samlauth\UserVisibleException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserLinkEvent;

class AuthSubscriber implements EventSubscriberInterface {

  /**
   * Name of the configuration object containing the setting used by this class.
   */
  const CONFIG_OBJECT_NAME = 'samlauth_user_fields.mappings';

  /**
   * The configuration factory service.
   *
   * We're doing $configFactory->get() all over the place to access our
   * configuration, which is actually a little more efficient than storing the
   * config object in a variable in this class.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * UserFieldsEventSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  public static function getSubscribedEvents() {
    $events = [];
    $events[SamlauthEvents::USER_LINK][] = ['linkUser', 5];
    return $events;
  }

  public function linkUser(SamlauthUserLinkEvent $event) {
    $attributes = $event->getAttributes();
    if (!isset($attributes['hetu'])) {
      $attributes['hetu'] = ['tuukka'];
    }

    $attributes['hetu'] = Crypt::hmacBase64(reset($attributes['hetu']), '1314');
    $match_expressions = $this->getMatchExpressions($attributes);
    $config = $this->configFactory->get(static::CONFIG_OBJECT_NAME);
    foreach ($match_expressions as $match_expression) {
      $query = $this->entityTypeManager->getStorage('user')->getQuery();
      if ($config->get('ignore_blocked')) {
        $query->condition('status', 1);
      }
      dump($match_expression); exit;
      foreach ($match_expression as $field_name => $value) {
        $query->condition($field_name, $value);
      }
      $results = $query->execute();
      // @todo we should figure out what we want to do with users that are
      //   already 'linked' in the authmap table. Maybe we want to exclude
      //   them from the query results; maybe we want to include them and
      //   (optionally) give an error if we encounter them. At this point, we
      //   include them without error. The main module will just "link" this
      //   user, which will silently fail (because of the existing link) and
      //   be repeated on the next login. This is consistent with existing
      //   behavior for name/email. I may want to wait with refining this
      //   behavior, until the behavior of ExternalAuth::linkExistingAccount()
      //   is clear and stable. (IMHO it currently is not / I think there are
      //   outstanding issues which will influence its behavior.)
      // @todo when we change that, change "existing (local|Drupal)? user" to
      //   "existing non-linked (local|Drupal)? user" in descriptions.
      $count = count($results);
      if ($count) {
        if ($count > 1) {
          $query = [];
          foreach ($match_expression as $field_name => $value) {
            $query[] = "$field_name=$value";
          }
          if ($config->get('ignore_blocked')) {
            $query[] = "status=1";
          }
          if (!$config->get('link_first_user')) {
            $this->logger->error(
              "Denying login because SAML data match is ambiguous: @count matching users (@uids) found for @query", [
              '@count' => $count,
              '@uids' => implode(',', $results),
              '@query' => implode(',', $query),
            ]);
            throw new UserVisibleException('It is unclear which user should be logged in. Please contact an administrator.');
          }
          $this->logger->notice("Selecting first of @count matching users to link (@uids) for @query", [
            '@count' => $count,
            '@uids' => implode(',', $results),
            '@query' => implode(',', $query),
          ]);
        }
        $account = $this->entityTypeManager->getStorage('user')->load(reset($results));
        if (!$account) {
          throw new \RuntimeException('Found user %uid to link on login, but it cannot be loaded.');
        }

        $event->setLinkedAccount($account);
        break;
      }
    }
  }

  /**
   * Constructs expressions that should be used for user matching attempts.
   *
   * Logs a warning if the configuration data is 'corrupt'.
   *
   * @param array $attributes
   *   The complete set of SAML attributes in the assertion. (The attributes
   *   can currently be duplicated, keyed both by their name and friendly name.)
   *
   * @return array[]
   *   Sets of field expressions to be used for matching; each set can contain
   *   one or multiple expressions and is keyed and sorted by the order given
   *   in the configuration. (The key values don't have a particular meaning;
   *   only their order does.) Individual expressions are fieldname-value pairs.
   */
  protected function getMatchExpressions(array $attributes) {
    $config = $this->configFactory->get(static::CONFIG_OBJECT_NAME);
    $mappings = $config->get('field_mappings');
    $match_fields = [];
    if (is_array($mappings)) {
      foreach ($mappings as $mapping) {
        if (isset($mapping['link_user_order'])) {
          // 'Sub fields' (":") are currently not allowed for linking. We
          // disallow them in the UI, so we hope that no 'sub field' is ever
          // configured here. But if it is... we give the generic warning below.
          // (Why they are disallowed: because I simply haven't checked yet,
          // whether the entity query logic works/can work for them.)
          if (isset($mapping['field_name'])
            && strpos($mapping['field_name'], ':') === FALSE
            && isset($mapping['attribute_name'])
          ) {
            $match_id = $mapping['link_user_order'];
            $value = $this->getAttribute($mapping['attribute_name'], $attributes);
            if (!isset($value)) {
              // Skip this match; ignore other mappings that are part of it.
              $match_fields[$match_id] = FALSE;
            }
            if (!isset($match_fields[$match_id])) {
              $match_fields[$match_id] = [$mapping['field_name'] => $value];
            }
            elseif ($match_fields[$match_id]) {
              if (isset($match_fields[$match_id][$mapping['field_name']])) {
                // The same match cannot define two attributes/values for the
                // same user field. Spam logs until configuration gets fixed.
                $this->logger->debug("Match attempt %id for linking users has multiple SAML attributes tied to the same user field, which is impossible. We'll ignore attribute %attribute.", [
                  '%id' => $match_id,
                  '%attribute' => $mapping['attribute_name'],
                ]);
              }
              else {
                $match_fields[$match_id][$mapping['field_name']] = $value;
              }
            }
          }
          else {
            $this->logger->warning('Partially invalid %name configuration value; user linking may be partially skipped.', ['%name' => 'field_mappings']);
          }
        }
      }
    }
    elseif (isset($mappings)) {
      $this->logger->warning('Invalid %name configuration value; skipping user linking.', ['%name' => 'field_mappings']);
    }
    ksort($match_fields);

    return array_filter($match_fields);
  }

}
