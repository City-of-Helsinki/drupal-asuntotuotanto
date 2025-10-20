<?php

namespace Drupal\asu_noindex\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class NoindexResponseSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public static function getSubscribedEvents(): array {
    return [ KernelEvents::RESPONSE => ['onResponse', -100] ];
  }

  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $indexed = (array) ($this->configFactory->get('asu_noindex.settings')->get('indexed_langs') ?? []);
    $lang = $this->languageManager->getCurrentLanguage()->getId();

    if (in_array($lang, $indexed, true)) {
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
