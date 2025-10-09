<?php

namespace Drupal\asu_application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Renders per-project application sync summary (UI + CSV export).
 */
class ApplicationSummaryController extends ControllerBase {

  /**
   * Summary table for a given project.
   *
   * @param int $node
   *   Node ID of the project.
   *
   * @return array
   *   Render array.
   */
  public function summary($node) {
    $node_entity = $this->entityTypeManager()->getStorage('node')->load($node);
    if ($node_entity === NULL) {
      throw new AccessDeniedHttpException();
    }

    $rows = $this->buildSummaryRows((int) $node);

    $header = [
      $this->t('ID'),
      $this->t('UID'),
      $this->t('Name'),
      $this->t('Email'),
      $this->t('Created'),
      $this->t('Changed'),
      $this->t('Create→Django'),
      $this->t('Locked'),
      $this->t('Backend ID'),
      $this->t('Error'),
      $this->t('Category'),
      $this->t('Jälkihakemus'),
      $this->t('URL'),
    ];

    $build['actions'] = [
      '#type' => 'container',
      'csv' => [
        '#type' => 'link',
        '#title' => $this->t('Export CSV'),
        '#url' => Url::fromRoute('asu_application.summary_project_csv', ['node' => $node]),
        '#attributes' => ['class' => ['button', 'button--small']],
      ],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => array_map(function (array $r) {
        $email_link = '';
        if ($r['user_mail'] !== '') {
          $email_link = Link::fromTextAndUrl(
            $r['user_mail'],
            Url::fromUri('mailto:' . $r['user_mail'])
          )->toString();
        }

        $url_link = '';
        if ($r['url'] !== '') {
          $url_link = Link::fromTextAndUrl(
            $this->t('Application'),
            Url::fromUri('internal:' . $r['url'])
          )->toString();
        }

        return [
          $r['id'],
          $r['uid'],
          $r['user_name'] ?: '—',
          $email_link ?: '—',
          $r['created_iso'],
          $r['changed_iso'],
          $r['create_to_django_iso'],
          $r['locked'],
          $r['backend_id'],
          $r['error_preview'] ?: '—',
          $r['category'],
          ($r['jalki'] ? '✓' : '—'),
          $url_link,
        ];
      }, $rows),
      '#empty' => $this->t('No applications found.'),
    ];

    $build['legend'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Category legend'),
      '#items' => [
        ['#markup' => '<code>sent_ok</code> — lukittu tai backend_id on asetettu'],
        ['#markup' => '<code>send_attempted_failed</code> — create_to_django asetettu tai error on olemassa'],
        ['#markup' => '<code>skeleton</code> — tyhjä luonnos (ei asuntoja, ei sopimustickejä, created==changed)'],
        ['#markup' => '<code>editing_draft</code> — luonnos muokkauksessa'],
      ],
      '#attributes' => ['class' => ['category-legend']],
    ];


    return $build;
  }

  /**
   * CSV export for the summary.
   *
   * @param int $node
   *   Node ID of the project.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   CSV response.
   */
  public function summaryCsv($node) {
    $rows = $this->buildSummaryRows((int) $node);

    $out = "\"id\";\"uid\";\"name\";\"email\";\"created_iso\";\"changed_iso\";\"create_to_django_iso\";\"locked\";\"backend_id\";\"error\";\"category\";\"jalkihakemus\";\"url\"\n";
    foreach ($rows as $r) {
      $line = [
        $r['id'],
        $r['uid'],
        str_replace('"', '""', $r['user_name']),
        str_replace('"', '""', $r['user_mail']),
        $r['created_iso'],
        $r['changed_iso'],
        $r['create_to_django_iso'],
        (string) $r['locked'],
        str_replace('"', '""', $r['backend_id']),
        str_replace('"', '""', $r['error_full']),
        $r['category'],
        (string) $r['jalki'],
        str_replace('"', '""', $r['url']),
      ];
      $out .= '"' . implode('";"', $line) . '"' . "\n";
    }

    $resp = new Response($out);
    $resp->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $resp->headers->set('Content-Disposition', 'attachment; filename="application_summary_' . $node . '.csv"');

    return $resp;
  }

  /**
   * Build raw rows for summary table and CSV.
   *
   * @param int $nid
   *   Project node ID.
   *
   * @return array
   *   Rows with normalized values for UI/CSV.
   */
  protected function buildSummaryRows(int $nid): array {
    $st = $this->entityTypeManager()->getStorage('asu_application');
    $ids = $st->getQuery()
      ->accessCheck(FALSE)
      ->condition('project_id', $nid)
      ->condition('status', 1)
      ->execute();

    $apps = $st->loadMultiple($ids);
    $rows = [];

    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->entityTypeManager()->getStorage('node')->load($nid);

    // Project-level data: end time & flag "can apply afterwards".
    $end_iso = '';
    $end_ts = 0;
    if ($node instanceof NodeInterface && $node->hasField('field_application_end_time') && !$node->get('field_application_end_time')->isEmpty()) {
      $end_iso = (string) ($node->get('field_application_end_time')->value ?? '');
      if ($end_iso !== '') {
        $ts = strtotime($end_iso);
        if ($ts !== FALSE) {
          $end_ts = (int) $ts;
        }
      }
    }

    foreach ($apps as $a) {
      $cr = (int) ($a->get('created')->value ?? 0);
      $ch = (int) ($a->get('changed')->value ?? 0);

      // Owner (who submitted).
      $uid = (string) ($a->get('uid')->target_id ?? '');
      $owner = method_exists($a, 'getOwner') ? $a->getOwner() : NULL;
      $user_name = $owner ? $owner->getDisplayName() : '';
      $user_mail = $owner ? (string) $owner->getEmail() : '';

      // Used only for category (skeleton/editing_draft).
      $apts = $a->get('apartment')->getValue();
      $aptc = is_array($apts) ? count(array_filter($apts, function ($x) {
        return !empty($x['id']);
      })) : 0;
      $agree = (int) ($a->get('field_agreement_policy')->value ?? 0) + (int) ($a->get('field_data_agreement_policy')->value ?? 0);

      // Send-to-Django moment (string or int).
      $ctd_raw = $a->get('create_to_django')->value ?? '';
      $ctd_iso = $this->tsToIso($ctd_raw);

      $locked = (int) ($a->get('field_locked')->value ?? 0);
      $bid = $a->get('field_backend_id')->value ?? '';

      // Error content for UI (preview) and CSV (full).
      $errv = $a->get('error')->value ?? '';
      $err_text = (string) $errv;
      $err_preview = $this->preview($err_text);

      // Category (same logic as earlier).
      if ($locked === 1 || $bid !== '') {
        $cat = 'sent_ok';
      }
      elseif ($ctd_raw !== '' || $err_text !== '') {
        $cat = 'send_attempted_failed';
      }
      else {
        $skeleton = ($aptc === 0 && $agree === 0 && $cr > 0 && $cr === $ch);
        $cat = $skeleton ? 'skeleton' : 'editing_draft';
      }

      $url = '';
      try {
        $url = $a->toUrl('canonical')->toString();
      }
      catch (\Throwable $e) {
        $url = '';
      }

      // Late submission (Jälkihakemus): created after end time.
      $jalki = 0;
      if ($end_ts > 0 && $cr > $end_ts) {
        $jalki = 1;
      }

      $rows[] = [
        'id' => (int) $a->id(),
        'uid' => $uid,
        'user_name' => $user_name,
        'user_mail' => $user_mail,
        'created_iso' => $cr ? date('c', $cr) : '',
        'changed_iso' => $ch ? date('c', $ch) : '',
        'create_to_django_iso' => $ctd_iso,
        'locked' => $locked,
        'backend_id' => (string) $bid,
        'error_preview' => $err_preview,
        'error_full' => $err_text,
        'category' => $cat,
        'jalki' => $jalki,
        'url' => (string) $url,
      ];
    }

    // Sort: failed attempts first, then drafts, then sent_ok.
    usort($rows, function (array $a, array $b) {
      $order = [
        'send_attempted_failed' => 0,
        'editing_draft' => 1,
        'skeleton' => 2,
        'sent_ok' => 3,
      ];
      $ord = ($order[$a['category']] <=> $order[$b['category']]);
      return $ord ?: strcmp($a['created_iso'], $b['created_iso']);
    });

    return $rows;
  }

  /**
   * Short preview helper (truncate long text).
   *
   * @param string $text
   *   Text to preview.
   * @param int $len
   *   Max length.
   *
   * @return string
   *   Trimmed text with ellipsis if needed.
   */
  protected function preview(string $text, int $len = 160): string {
    $text = trim($text);
    if ($text === '') {
      return '';
    }
    return (mb_strlen($text) > $len) ? (mb_substr($text, 0, $len - 1) . '…') : $text;
  }

  /**
   * Convert integer timestamp or string value to ISO string.
   *
   * @param mixed $v
   *   Value: int timestamp or string.
   *
   * @return string
   *   ISO 8601 string or empty string.
   */
  protected function tsToIso($v): string {
    if ($v === '' || $v === NULL) {
      return '';
    }
    if (is_numeric($v)) {
      $ts = (int) $v;
      return ($ts > 0) ? date('c', $ts) : (string) $v;
    }
    return (string) $v;
  }

  /**
   * Get first non-empty field value from candidate field names.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   * @param array $candidates
   *   Field machine name candidates.
   *
   * @return mixed
   *   Field value or NULL.
   */
  protected function nodeFieldValue(NodeInterface $node, array $candidates) {
    foreach ($candidates as $name) {
      if ($node->hasField($name) && !$node->get($name)->isEmpty()) {
        $v = $node->get($name)->getValue();
        if (is_array($v) && isset($v[0]['value'])) {
          return $v[0]['value'];
        }
        if (is_array($v) && isset($v[0]['target_id'])) {
          return $v[0]['target_id'];
        }
        return $v;
      }
    }
    return NULL;
  }

}
