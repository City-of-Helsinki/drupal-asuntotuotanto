<?php
namespace Drupal\asu_content\Controller;

use Drupal\asu_api\Api\BackendApi\Request\GetIntegrationStatusRequest;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders integration status summary (UI + CSV export).
 */
class IntegrationSummaryController extends ControllerBase {

  /**
   * Map public sort keys -> row array keys.
   *
   * @var array<string,string>
   */
  private const SORTABLE = [
    'integration' => 'integration_name',
    'status' => 'status',
    'project_housing_company' => 'project_housing_company',
    'apartment_address' => 'apartment_address',
    'project_url' => 'project_url',
    'url' => 'url',
    'missing_fields' => 'missing_fields',
  ];

  /**
   * Summary table for integration status.
   *
   * Builds a sortable HTML table of apartments marked for integration export.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request (reads 'sort' and 'dir' query parameters).
   *
   * @return array
   *   Render array for the summary table.
   */
  public function summary(Request $request) {
    $rows = $this->buildSummaryRows();

    [$sort, $dir] = $this->getSortParams();
    $this->applySort($rows, $sort, $dir);

    // Helper to build header link with toggle dir and arrow.
    $headerCell = function (string $key, string $label) use ($sort, $dir) {
      $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
      $arrow = '';
      if ($sort === $key) {
        $arrow = $dir === 'asc' ? ' ▲' : ' ▼';
      }
      $url = Url::fromRoute('asu_content.summary_integrations', [], [
        'query' => ['sort' => $key, 'dir' => $nextDir],
      ]);

      return Link::fromTextAndUrl(
        $this->t('@label@arrow', ['@label' => $label, '@arrow' => $arrow]),
        $url
      )->toString();
    };

    $header = [
      $headerCell('integration', $this->t('Integration')),
      $headerCell('status', $this->t('Status')),
      $headerCell('project_housing_company', $this->t('Project Name')),
      $headerCell('apartment_address', $this->t('Apartment Address')),
      $headerCell('project_url', $this->t('Project URL')),
      $headerCell('url', $this->t('Apartment URL')),
      $headerCell('missing_fields', $this->t('Missing Fields')),
    ];

    $build['actions'] = [
      '#type' => 'container',
      'csv' => [
        '#type' => 'link',
        '#title' => $this->t('Export CSV'),
        '#url' => Url::fromRoute('asu_content.summary_integrations_csv', [], [
          'query' => ['sort' => $sort, 'dir' => $dir],
        ]),
        '#attributes' => ['class' => ['button', 'button--small']],
      ],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => array_map(function (array $r) {
        // Format project URL link
        $project_url_link = '';
        if (isset($r['project_url']) && $r['project_url'] !== '' && $r['project_url'] !== NULL) {
          $project_url_link = Link::fromTextAndUrl(
            $this->t('Project'),
            Url::fromUri($r['project_url'])
          )->toString();
        }

        // Format apartment URL link
        $url_link = '';
        if (isset($r['url']) && $r['url'] !== '' && $r['url'] !== NULL) {
          $url_link = Link::fromTextAndUrl(
            $this->t('Apartment'),
            Url::fromUri($r['url'])
          )->toString();
        }

        // Format status with visual indicator
        $status_display = $r['status'];
        if (isset($r['status_key']) && $r['status_key'] === 'success') {
          $status_display = '✓ ' . $status_display;
        }
        elseif (isset($r['status_key']) && $r['status_key'] === 'fail') {
          $status_display = '✗ ' . $status_display;
        }

        // Format missing fields
        $missing_fields_display = '—';
        if (isset($r['missing_fields']) && $r['missing_fields'] !== '' && $r['missing_fields'] !== NULL) {
          $fields = explode(', ', $r['missing_fields']);
          $fields = array_filter(array_map('trim', $fields));
          if (!empty($fields)) {
            $missing_fields_display = implode(', ', $fields);
          }
        }

        return [
          $r['integration_name'],
          $status_display,
          $r['project_housing_company'] ?: '—',
          $r['apartment_address'] ?: '—',
          $project_url_link ?: '—',
          $url_link ?: '—',
          $missing_fields_display,
        ];
      }, $rows),
      '#empty' => $this->t('No apartments found.'),
    ];

    // Legend/help text
    $build['legend'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Status legend'),
      '#items' => [
        ['#markup' => '<strong>' . $this->t('Success') . '</strong> — ' . $this->t('Apartment has all required fields and is ready for export')],
        ['#markup' => '<strong>' . $this->t('Fail') . '</strong> — ' . $this->t('Apartment is missing required fields (listed in Missing Fields column)')],
      ],
      '#attributes' => ['class' => ['integration-legend']],
    ];

    return $build;
  }

  /**
   * CSV export for the summary.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request (used for sort params).
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   CSV response.
   */
  public function summaryCsv(Request $request) {
    $rows = $this->buildSummaryRows();
    [$sort, $dir] = $this->getSortParams();
    $this->applySort($rows, $sort, $dir);

    $out = "\"integration\";\"status\";\"project_housing_company\";\"apartment_address\";\"project_url\";\"url\";\"missing_fields\"\n";
    foreach ($rows as $r) {
      $line = [
        str_replace('"', '""', $r['integration_name']),
        str_replace('"', '""', $r['status']),
        str_replace('"', '""', $r['project_housing_company']),
        str_replace('"', '""', $r['apartment_address']),
        str_replace('"', '""', $r['project_url']),
        str_replace('"', '""', $r['url']),
        str_replace('"', '""', $r['missing_fields']),
      ];
      $out .= '"' . implode('";"', $line) . '"' . "\n";
    }

    $resp = new Response($out);
    $resp->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $resp->headers->set('Content-Disposition', 'attachment; filename="integration_summary.csv"');

    return $resp;
  }

  /**
   * Build raw rows for summary table and CSV.
   *
   * @return array
   *   Rows with normalized values for UI/CSV.
   */
  protected function buildSummaryRows(): array {
    /** @var \Drupal\asu_api\Api\BackendApi\BackendApi $api */
    $api = \Drupal::service('asu_api.backendapi');
    $request = new GetIntegrationStatusRequest();
    /** @var \Drupal\asu_api\Api\BackendApi\Response\GetIntegrationStatusResponse $response */
    $response = $api->send($request);
    $data = $response->getContent();

    $rows = [];

    // Ensure $data is an array.
    if (!is_array($data)) {
      return $rows;
    }

    // Iterate through each integration (etuovi, oikotie, etc.)
    foreach ($data as $integration_name => $integration_data) {
      if (!is_array($integration_data)) {
        continue;
      }

      // Process success items
      if (isset($integration_data['success']) && is_array($integration_data['success'])) {
        foreach ($integration_data['success'] as $item) {
          $missing_fields = '';
          if (isset($item['missing_fields']) && is_array($item['missing_fields'])) {
            $missing_fields = implode(', ', $item['missing_fields']);
          }
          $rows[] = [
            'integration_name' => (string) $integration_name,
            'status' => $this->t('Success'),
            'status_key' => 'success',
            'uuid' => (string) ($item['uuid'] ?? ''),
            'project_uuid' => (string) ($item['project_uuid'] ?? ''),
            'project_housing_company' => (string) ($item['project_housing_company'] ?? ''),
            'apartment_address' => (string) ($item['apartment_address'] ?? ''),
            'project_url' => (string) ($item['project_url'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'missing_fields' => $missing_fields,
          ];
        }
      }

      // Process fail items
      if (isset($integration_data['fail']) && is_array($integration_data['fail'])) {
        foreach ($integration_data['fail'] as $item) {
          $missing_fields = '';
          if (isset($item['missing_fields']) && is_array($item['missing_fields'])) {
            $missing_fields = implode(', ', $item['missing_fields']);
          }

          $rows[] = [
            'integration_name' => (string) $integration_name,
            'status' => $this->t('Fail'),
            'status_key' => 'fail',
            'uuid' => (string) ($item['uuid'] ?? ''),
            'project_uuid' => (string) ($item['project_uuid'] ?? ''),
            'project_housing_company' => (string) ($item['project_housing_company'] ?? ''),
            'apartment_address' => (string) ($item['apartment_address'] ?? ''),
            'project_url' => (string) ($item['project_url'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'missing_fields' => $missing_fields,
          ];
        }
      }
    }

    return $rows;
  }

  /**
   * Get sanitized sort params from current request.
   *
   * @return array{0:string,1:string}
   *   [$sort, $dir] where $dir is 'asc'|'desc'.
   */
  protected function getSortParams(): array {
    /** @var \Symfony\Component\HttpFoundation\Request $request */
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal
    $request = \Drupal::requestStack()->getCurrentRequest();

    $sort = (string) ($request->query->get('sort') ?? 'integration');
    $dir = strtolower((string) ($request->query->get('dir') ?? 'asc'));

    if (!isset(self::SORTABLE[$sort])) {
      $sort = 'integration';
    }
    if (!in_array($dir, ['asc', 'desc'], TRUE)) {
      $dir = 'asc';
    }
    return [$sort, $dir];
  }

  /**
   * Sort rows in-place by a whitelisted key and direction.
   *
   * @param array $rows
   *   Rows to sort (by reference).
   * @param string $sort
   *   Public sort key, see self::SORTABLE.
   * @param string $dir
   *   Type 'asc' or 'desc'.
   */
  protected function applySort(array &$rows, string $sort, string $dir): void {
    $key = self::SORTABLE[$sort] ?? 'integration_name';
    $mult = ($dir === 'desc') ? -1 : 1;

    usort($rows, function (array $a, array $b) use ($key, $mult) {
      $va = $a[$key] ?? '';
      $vb = $b[$key] ?? '';

      // Case-insensitive string comparison.
      return $mult * strcasecmp((string) $va, (string) $vb);
    });
  }

  /**
   * Calculate statistics from rows.
   *
   * @param array $rows
   *   Array of row data.
   *
   * @return array
   *   Statistics array with 'success' and 'fail' counts.
   */
  protected function calculateStats(array $rows): array {
    $stats = ['success' => 0, 'fail' => 0];
    foreach ($rows as $row) {
      if (isset($row['status_key'])) {
        if ($row['status_key'] === 'success') {
          $stats['success']++;
        }
        elseif ($row['status_key'] === 'fail') {
          $stats['fail']++;
        }
      }
    }
    return $stats;
  }

}