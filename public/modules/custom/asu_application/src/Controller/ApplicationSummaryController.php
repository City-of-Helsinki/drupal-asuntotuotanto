<?php

namespace Drupal\asu_application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ApplicationSummaryController extends ControllerBase {

  public function summary($node) {
    $node_entity = $this->entityTypeManager()->getStorage('node')->load($node);
    if (!$node_entity) {
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
      '#rows' => array_map(function($r) {
        $email_link = $r['user_mail'] !== '' ? \Drupal\Core\Link::fromTextAndUrl(
          $r['user_mail'],
          \Drupal\Core\Url::fromUri('mailto:' . $r['user_mail'])
        )->toString() : '';

        $url_link = $r['url'] !== '' ? \Drupal\Core\Link::fromTextAndUrl(
          $this->t('Application'),
          \Drupal\Core\Url::fromUri('internal:' . $r['url'])
        )->toString() : '';

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
      '#markup' => '<p><strong>Category legend:</strong> ' .
        '<code>sent_ok</code> — lukittu tai backend_id on asetettu; ' .
        '<code>send_attempted_failed</code> — create_to_django asetettu tai error on olemassa; ' .
        '<code>skeleton</code> — tyhjä luonnos (ei asuntoja, ei sopimustickejä, created==changed); ' .
        '<code>editing_draft</code> — luonnos muokkauksessa.' .
      '</p>',
    ];

    return $build;
  }

  public function summaryCsv($node) {
    $rows = $this->buildSummaryRows((int) $node);

    $out = "\"id\";\"uid\";\"name\";\"email\";\"created_iso\";\"changed_iso\";\"create_to_django_iso\";\"locked\";\"backend_id\";\"error\";\"category\";\"jalkihakemus\";\"url\"\n";
    foreach ($rows as $r) {
      $line = [
      $r['id'],
      $r['uid'],
      str_replace('"','""',$r['user_name']),
      str_replace('"','""',$r['user_mail']),
      $r['created_iso'],
      $r['changed_iso'],
      $r['create_to_django_iso'],
      (string) $r['locked'],
      str_replace('"','""',$r['backend_id']),
      str_replace('"','""',$r['error_full']),
      $r['category'],
      (string) $r['jalki'],
      str_replace('"','""',$r['url']),
    ];
    $out .= '"' . implode('";"', $line) . '"' . "\n";
    }

    $resp = new Response($out);
    $resp->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $resp->headers->set('Content-Disposition', 'attachment; filename="application_summary_'.$node.'.csv"');
    return $resp;
  }

  protected function preview(string $text, int $len = 160): string {
    $text = trim($text);
    if ($text === '') return '';
    return mb_strlen($text) > $len ? (mb_substr($text, 0, $len - 1) . '…') : $text;
  }

  protected function tsToIso($v): string {
    if ($v === '' || $v === NULL) return '';
    if (is_numeric($v)) {
      $ts = (int) $v;
      return $ts > 0 ? date('c', $ts) : (string) $v;
    }
    return (string) $v;
  }


  protected function buildSummaryRows(int $nid): array {
    $st = $this->entityTypeManager()->getStorage('asu_application');
    $ids = $st->getQuery()->accessCheck(FALSE)
      ->condition('project_id', $nid)
      ->condition('status', 1)
      ->execute();

    $apps = $st->loadMultiple($ids);
    $rows = [];
    $node = $this->entityTypeManager()->getStorage('node')->load($nid);

    $end_iso = '';
    if ($node && $node->hasField('field_application_end_time') && !$node->get('field_application_end_time')->isEmpty()) {
      $end_iso = (string) ($node->get('field_application_end_time')->value ?? '');
    }
    $end_ts = $end_iso ? strtotime($end_iso) : 0;

    $can_after = false;
    if ($node && $node->hasField('field_can_apply_afterwards') && !$node->get('field_can_apply_afterwards')->isEmpty()) {
      $can_after = (bool) ((int) ($node->get('field_can_apply_afterwards')->value ?? 0));
    }


    foreach ($apps as $a) {
      $cr = (int) ($a->get('created')->value ?? 0);
      $ch = (int) ($a->get('changed')->value ?? 0);

      $uid = (string) ($a->get('uid')->target_id ?? '');
      $owner = method_exists($a, 'getOwner') ? $a->getOwner() : NULL;
      $user_name = $owner ? $owner->getDisplayName() : '';
      $user_mail = $owner ? (string) $owner->getEmail() : '';

      $apts = $a->get('apartment')->getValue();
      $aptc = is_array($apts) ? count(array_filter($apts, fn($x) => !empty($x['id']))) : 0;
      $agree = (int) ($a->get('field_agreement_policy')->value ?? 0) + (int) ($a->get('field_data_agreement_policy')->value ?? 0);

      $ctd_raw = $a->get('create_to_django')->value ?? '';
      $ctd_iso = $this->tsToIso($ctd_raw);

      $locked = (int) ($a->get('field_locked')->value ?? 0);
      $bid = $a->get('field_backend_id')->value ?? '';

      $errv = $a->get('error')->value ?? '';
      $err_text = (string) $errv;
      $err_preview = $this->preview($err_text);

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

      $url = NULL; try { $url = $a->toUrl('canonical')->toString(); } catch (\Throwable $e) {}
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

    usort($rows, function($a, $b) {
      $order = ['send_attempted_failed' => 0, 'editing_draft' => 1, 'skeleton' => 2, 'sent_ok' => 3];
      return ($order[$a['category']] <=> $order[$b['category']])
        ?: strcmp($a['created_iso'], $b['created_iso']);
    });

    return $rows;
  }
}
