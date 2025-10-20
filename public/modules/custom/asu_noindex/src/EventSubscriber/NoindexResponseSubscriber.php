<?php

namespace Drupal\asu_noindex\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to kernel responses to add noindex for non-indexed languages.
 */
final class NoindexResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new NoindexResponseSubscriber.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -100]];
  }

  /**
   * Adds X-Robots-Tag and a meta robots tag for non-indexed languages.
   *
   * If current language isn't listed in asu_noindex.settings:indexed_langs,
   * sets the "X-Robots-Tag: noindex, nofollow" header on the response. For
   * HTML responses, also injects a corresponding
   * <meta name="robots" content="noindex, nofollow"> tag into the <head>.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $indexed = (array) ($this->configFactory->get('asu_noindex.settings')->get('indexed_langs') ?? []);
    $lang = $this->languageManager->getCurrentLanguage()->getId();

    if (in_array($lang, $indexed, TRUE)) {
      return;
    }

    $response = $event->getResponse();
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    $ct = $response->headers->get('Content-Type');
    if ($ct && str_contains(strtolower($ct), 'text/html')) {
      $content = $response->getContent();
      if (is_string($content) && str_contains($content, '</head>') && !str_contains($content, 'name="robots"')) {
        $meta = '<meta name="robots" content="noindex, nofollow">';
        $response->setContent(str_replace('</head>', "  {$meta}\n</head>", $content));
      }
    }
  }

}
