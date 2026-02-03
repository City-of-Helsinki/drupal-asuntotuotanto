<?php

namespace Drupal\asu_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Provides a filter form for the Integration Summary page.
 */
class IntegrationSummaryFilterForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'asu_content_integration_summary_filter_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $options
   *   Available filter options for the summary list.
   *
   * @return array
   *   The built form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $options = []) {
    $sanitized_options = [];
    foreach ($options as $value => $label) {
      $sanitized_options[$value] = $label;
    }

    $request = $this->requestStack->getCurrentRequest();
    $filter_value = $request->query->get('filter_value', '');

    $form['filter_value'] = [
      '#type' => 'select',
      '#title' => $this->t('Project'),
      '#options' => ['' => $this->t('- All -')] + $sanitized_options,
      '#default_value' => $filter_value,
      '#attributes' => [
        'class' => ['asu-content-integration-summary-filter-select'],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if ($filter_value !== '' && $filter_value !== NULL) {
      $query = $request->query->all();
      unset($query['filter_value'], $query['clear_filter']);

      $form['actions']['clear_filter'] = [
        '#type' => 'link',
        '#title' => $this->t('Show all'),
        '#url' => Url::fromRoute('<current>', [], [
          'query' => $query,
        ]),
        '#attributes' => [
          'class' => ['button', 'button--secondary'],
          'style' => 'margin-left: 0.5em;',
        ],
        '#weight' => 1,
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    // Ensure filter selection is reflected in URL.
    $form['#method'] = 'get';

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Filtering is handled by query string; no submit logic is needed.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Filtering handled with GET.
  }

}
