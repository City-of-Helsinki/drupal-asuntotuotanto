<?php

namespace Drupal\asu_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a filter form for the Integration Summary page.
 */
class IntegrationSummaryFilterForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'asu_content_integration_summary_filter_form';
    }

    /**
     * {@inheritdoc}
     *
     * @param array $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     * @param array $options
     *   The available filter options for the summary list.
     *
     * @return array
     *   The built form array.
     */
    public function buildForm(array $form, FormStateInterface $form_state, array $options = [])
    {
        $sanitized_options = [];
        foreach ($options as $value => $label) {
            $sanitized_options[$value] = $label;
        }

        
        $filter_value = \Drupal::request()->query->get('filter_value', '');

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

            $form['actions']['clear_filter'] = [
                '#type' => 'link',
                '#title' => $this->t('Show all'),
                '#url' => \Drupal\Core\Url::fromRoute('<current>', [], [
                    'query' => array_diff_key(\Drupal::request()->query->all(), ['filter_value' => '', 'clear_filter' => '']),
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


        // Log the selected filter value and available options for debugging.
        \Drupal::logger('asu_content')->debug('IntegrationSummaryFilterForm filter_value: @filter_value; options: @options', [
            '@filter_value' => $filter_value ?? '(empty)',
            '@options' => implode(', ', array_keys($sanitized_options)),
        ]);
        return $form;
    }

    /**
     * {@inheritdoc}
     *
     * Filtering is handled by query string; no submit logic is needed.
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // Filtering handled with GET.
    }
}
