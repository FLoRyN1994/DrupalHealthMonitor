<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\Entity\EntityInterface;

/**
 * Builds the monitored site administration list.
 */
final class SiteListBuilder extends HealthMonitorEntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table header.
   */
  public function buildHeader(): array {
    $header['name'] = $this->t('Name');
    $header['client'] = $this->t('Client ID');
    $header['environment'] = $this->t('Environment');
    $header['status'] = $this->t('Status');
    $header['health_score'] = $this->t('Health score');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table row.
   */
  public function buildRow(EntityInterface $entity): array {
    $row['name'] = $entity->toLink(NULL, 'edit-form');
    $row['client'] = $this->fieldValue($entity, 'client');
    $row['environment'] = $this->fieldValue($entity, 'environment');
    $row['status'] = $this->fieldValue($entity, 'status');
    $row['health_score'] = $this->fieldValue($entity, 'health_score');
    return $row + parent::buildRow($entity);
  }

}
