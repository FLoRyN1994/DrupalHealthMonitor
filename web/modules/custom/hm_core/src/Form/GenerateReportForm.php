<?php

declare(strict_types=1);

namespace Drupal\hm_core\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Report\ReportGenerationException;
use Drupal\hm_core\Report\SiteReportService;

/**
 * Generates a client maintenance report for one site.
 */
final class GenerateReportForm extends FormBase {

  /**
   * Creates a report generation form.
   */
  public function __construct(
    protected SiteReportService $reportService,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $hmLoggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hm_core_generate_report';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\hm_core\Entity\Site|null $hm_site
   *   The site from the route.
   *
   * @return array<string, mixed>
   *   The form render array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Site $hm_site = NULL): array {
    if (!$hm_site instanceof Site) {
      throw new \InvalidArgumentException('A monitored site is required.');
    }
    $form_state->set('site_id', $hm_site->id());
    $form['description'] = [
      '#markup' => $this->t(
        '<p>Generate a client-ready report for <strong>@site</strong> from its current open findings. The effort estimate is calculated deterministically; AI writes only the summary, update digest, and Drupal 12 suggestions.</p>',
        ['@site' => $hm_site->label()],
      ),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Generate report'),
        '#button_type' => 'primary',
      ],
    ];

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
    $site = $this->entityTypeManager
      ->getStorage('hm_site')
      ->load($formState->get('site_id'));
    if (!$site instanceof Site) {
      $this->messenger()->addError($this->t('The monitored site no longer exists.'));
      return;
    }

    try {
      $report = $this->reportService->generate($site);
      $this->messenger()->addStatus($this->t('The client report was generated.'));
      $formState->setRedirectUrl($report->toUrl());
    }
    catch (ReportGenerationException $exception) {
      $this->hmLoggerFactory->get('hm_core')->error('AI report generation failed for Site @site: @message', [
        '@site' => $site->id(),
        '@message' => $exception->getMessage(),
      ]);
      $this->messenger()->addError($this->t('The report could not be generated: @message', [
        '@message' => $exception->getMessage(),
      ]));
    }
  }

}
