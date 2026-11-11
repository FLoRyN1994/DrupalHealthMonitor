<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\Entity\EntityInterface;

/**
 * Builds the AI report administration list.
 */
final class AiReportListBuilder extends HealthMonitorEntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table header.
   */
  public function buildHeader(): array {
    $header['title'] = $this->t('Report');
    $header['site'] = $this->t('Site ID');
    $header['generated_at'] = $this->t('Generated');
    $header['effort_estimate'] = $this->t('Hours');
    $header['model_used'] = $this->t('Model');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table row.
   */
  public function buildRow(EntityInterface $entity): array {
    $row['title'] = $entity->toLink();
    $row['site'] = $this->fieldValue($entity, 'site');
    $row['generated_at'] = $this->fieldValue($entity, 'generated_at');
    $row['effort_estimate'] = $this->fieldValue($entity, 'effort_estimate');
    $row['model_used'] = $this->fieldValue($entity, 'model_used');
    return $row + parent::buildRow($entity);
  }

}
