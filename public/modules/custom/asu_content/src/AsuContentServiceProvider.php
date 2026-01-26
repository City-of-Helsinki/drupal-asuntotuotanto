<?php

namespace Drupal\asu_content;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Service provider adjustments for asu_content module.
 */
final class AsuContentServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (!$container->hasParameter('monolog.channel_handlers')) {
      return;
    }

    $handlers = $container->getParameter('monolog.channel_handlers');
    $changed = FALSE;

    foreach ($handlers as $channel => &$definition) {
      if (!is_array($definition)) {
        continue;
      }
      if (!isset($definition['handlers']) || !is_array($definition['handlers'])) {
        continue;
      }

      foreach ($definition['handlers'] as &$handler) {
        if (!is_array($handler)) {
          continue;
        }
        $processor_list = $handler['processors'] ?? [];
        if (!in_array('asu_context_sanitizer', $processor_list, TRUE)) {
          $processor_list[] = 'asu_context_sanitizer';
          $handler['processors'] = $processor_list;
          $changed = TRUE;
        }
      }
      unset($handler);
    }
    unset($definition);

    if ($changed) {
      $container->setParameter('monolog.channel_handlers', $handlers);
    }
  }

}
