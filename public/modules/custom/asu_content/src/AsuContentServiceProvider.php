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
    if ($this->addProcessorToHandlers($handlers)) {
      $container->setParameter('monolog.channel_handlers', $handlers);
    }
  }

  /**
   * Add asu_context_sanitizer processor to all handlers.
   *
   * @param array $handlers
   *   The handlers array to modify.
   *
   * @return bool
   *   TRUE if handlers were modified, FALSE otherwise.
   */
  private function addProcessorToHandlers(array &$handlers): bool {
    $changed = FALSE;

    foreach ($handlers as &$definition) {
      if (!is_array($definition) || !isset($definition['handlers'])) {
        continue;
      }

      if ($this->addProcessorToChannelHandlers($definition['handlers'])) {
        $changed = TRUE;
      }
    }

    return $changed;
  }

  /**
   * Add processor to channel handlers.
   *
   * @param array $channel_handlers
   *   The channel handlers array to modify.
   *
   * @return bool
   *   TRUE if any handler was modified, FALSE otherwise.
   */
  private function addProcessorToChannelHandlers(array &$channel_handlers): bool {
    if (!is_array($channel_handlers)) {
      return FALSE;
    }

    $changed = FALSE;
    foreach ($channel_handlers as &$handler) {
      if (is_array($handler) && $this->addProcessorToHandler($handler)) {
        $changed = TRUE;
      }
    }

    return $changed;
  }

  /**
   * Add processor to single handler.
   *
   * @param array $handler
   *   The handler array to modify.
   *
   * @return bool
   *   TRUE if handler was modified, FALSE otherwise.
   */
  private function addProcessorToHandler(array &$handler): bool {
    $processor_list = $handler['processors'] ?? [];
    if (in_array('asu_context_sanitizer', $processor_list, TRUE)) {
      return FALSE;
    }

    $processor_list[] = 'asu_context_sanitizer';
    $handler['processors'] = $processor_list;
    return TRUE;
  }

}
