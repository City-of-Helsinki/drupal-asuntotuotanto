<?php
namespace Drupal\asu_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class IntegrationSummaryController extends ControllerBase {

public function summary(Request $request) {
    // $node_entity = $this->entityTypeManager()->getStorage('node')->load($node);
    // if ($node_entity === NULL) {
    //   throw new AccessDeniedHttpException();
    // }

    // $rows = $this->buildSummaryRows((int) $node);

    // [$sort, $dir] = $this->getSortParams();
    // $this->applySort($rows, $sort, $dir);
    // Helper to build header link with toggle dir and arrow.
    // $headerCell = function (string $key, string $label) use ($sort, $dir) {
    //   $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    //   $arrow = '';
    //   if ($sort === $key) {
    //     $arrow = $dir === 'asc' ? ' ▲' : ' ▼';
    //   }

    //   return Link::fromTextAndUrl(
    //     $this->t('@label@arrow', ['@label' => $label, '@arrow' => $arrow]),
    //     Url::fromUri("")
    //   )->toString();
    // };

    $header = [
      // $headerCell('id', 'ID'),
      // $headerCell('uid', 'UID'),
      "test",
      "test2"
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => [],
      // '#rows' => array_map(function (array $r) {
      //   $email_link = '';
      //   if ($r['user_mail'] !== '') {
      //     $email_link = Link::fromTextAndUrl(
      //       $r['user_mail'],
      //       Url::fromUri('mailto:' . $r['user_mail'])
      //     )->toString();
      //   }

      //   $url_link = '';
      //   if ($r['url'] !== '') {
      //     $url_link = Link::fromTextAndUrl(
      //       $this->t('Application'),
      //       Url::fromUri('internal:' . $r['url'])
      //     )->toString();
      //   }

      //   return [
      //     $r['id'],
      //     $r['uid'],
      //     $r['user_name'] ?: '—',
      //     $email_link ?: '—',
      //     $r['created_iso'],
      //     $r['changed_iso'],
      //     $r['create_to_django_iso'],
      //     $r['locked'],
      //     $r['backend_id'],
      //     $r['error_preview'] ?: '—',
      //     $r['category'],
      //     ($r['jalki'] ? '✓' : '—'),
      //     $url_link,
      //   ];
      // }, $rows),
      '#empty' => $this->t('No synced apartments found.'),
    ];

    // $build['legend'] = [
    //   '#theme' => 'item_list',
    //   '#title' => $this->t('Category legend'),
    //   '#items' => [
    //     ['#markup' => '<code>sent_ok</code> — lukittu tai backend_id on asetettu'],
    //     ['#markup' => '<code>send_attempted_failed</code> — create_to_django asetettu tai error on olemassa'],
    //     ['#markup' => '<code>skeleton</code> — tyhjä luonnos (ei asuntoja, ei sopimustickejä, created==changed)'],
    //     ['#markup' => '<code>editing_draft</code> — luonnos muokkauksessa'],
    //   ],
    //   '#attributes' => ['class' => ['category-legend']],
    // ];

    return $build;
  }
}

?>