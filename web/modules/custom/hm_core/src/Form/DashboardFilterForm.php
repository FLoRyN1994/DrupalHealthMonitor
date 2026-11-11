<?php

declare(strict_types=1);

namespace Drupal\hm_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;

/**
 * Provides progressively enhanced dashboard filters.
 */
final class DashboardFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hm_core_dashboard_filters';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The form render array.
   */
  public function buildForm(array $form, FormStateInterface $formState): array {
    $query = $this->getRequest()->query;
    $form['#method'] = 'get';
    $form['#attributes']['class'][] = 'hm-dashboard-filters';

    $form['environment'] = [
      '#type' => 'select',
      '#title' => $this->t('Environment'),
      '#options' => [
        'all' => $this->t('All environments'),
        'prod' => $this->t('Production'),
        'stage' => $this->t('Staging'),
        'dev' => $this->t('Development'),
      ],
      '#default_value' => $this->allowedValue(
        $query->getString('environment', 'all'),
        ['all', 'prod', 'stage', 'dev'],
      ),
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Monitoring status'),
      '#options' => [
        'all' => $this->t('All statuses'),
        'active' => $this->t('Active'),
        'paused' => $this->t('Paused'),
        'disabled' => $this->t('Disabled'),
      ],
      '#default_value' => $this->allowedValue(
        $query->getString('status', 'all'),
        ['all', 'active', 'paused', 'disabled'],
      ),
    ];
    $form['health'] = [
      '#type' => 'select',
      '#title' => $this->t('Health'),
      '#options' => [
        'all' => $this->t('All health scores'),
        'healthy' => $this->t('Healthy (90–100)'),
        'attention' => $this->t('Needs attention (70–89)'),
        'critical' => $this->t('Critical (0–69)'),
      ],
      '#default_value' => $this->allowedValue(
        $query->getString('health', 'all'),
        ['all', 'healthy', 'attention', 'critical'],
      ),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Apply filters'),
      ],
      'reset' => [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => Url::fromRoute('hm_core.dashboard'),
        '#attributes' => ['class' => ['button']],
      ],
    ];
    $form['loading'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('Refreshing dashboard…'),
      '#attributes' => [
        'id' => 'hm-dashboard-loading',
        'class' => ['hm-dashboard-filters__loading', 'htmx-indicator'],
        'role' => 'status',
      ],
    ];

    $dashboardUrl = Url::fromRoute('hm_core.dashboard');
    $htmx = (new Htmx())
      ->get($dashboardUrl)
      ->onlyMainContent()
      ->select('#hm-dashboard-results')
      ->target('#hm-dashboard-results')
      ->swap('outerHTML')
      ->trigger(['change delay:200ms', 'submit'])
      ->pushUrl(TRUE)
      ->indicator('#hm-dashboard-loading')
      ->sync('this:replace');
    $htmx->applyTo($form);

    $resetHtmx = (new Htmx())
      ->get($dashboardUrl)
      ->onlyMainContent()
      ->select('#hm-dashboard-results')
      ->target('#hm-dashboard-results')
      ->swap('outerHTML')
      ->pushUrl($dashboardUrl)
      ->indicator('#hm-dashboard-loading');
    $resetHtmx->applyTo($form['actions']['reset']);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    // GET submissions are represented entirely by the query string.
  }

  /**
   * Returns a value only when it is in the allowlist.
   *
   * @param string $value
   *   The candidate value.
   * @param string[] $allowedValues
   *   Allowed values.
   */
  private function allowedValue(string $value, array $allowedValues): string {
    return in_array($value, $allowedValues, TRUE) ? $value : 'all';
  }

}
