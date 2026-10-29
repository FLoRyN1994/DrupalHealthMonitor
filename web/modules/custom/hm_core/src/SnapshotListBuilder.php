<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\Entity\EntityInterface;

/**
 * Builds the snapshot administration list.
 */
final class SnapshotListBuilder extends HealthMonitorEntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table header.
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['site'] = $this->t('Site ID');
    $header['checked_at'] = $this->t('Checked at');
    $header['core_version'] = $this->t('Drupal');
    $header['php_version'] = $this->t('PHP');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table row.
   */
  public function buildRow(EntityInterface $entity): array {
    $row['id'] = $entity->toLink((string) $entity->id(), 'edit-form');
    $row['site'] = $this->fieldValue($entity, 'site');
    $row['checked_at'] = $this->fieldValue($entity, 'checked_at');
    $row['core_version'] = $this->fieldValue($entity, 'core_version');
    $row['php_version'] = $this->fieldValue($entity, 'php_version');
    return $row + parent::buildRow($entity);
  }

}
