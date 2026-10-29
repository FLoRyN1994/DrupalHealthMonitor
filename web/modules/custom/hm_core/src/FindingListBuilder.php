<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\Entity\EntityInterface;

/**
 * Builds the finding administration list.
 */
final class FindingListBuilder extends HealthMonitorEntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table header.
   */
  public function buildHeader(): array {
    $header['project_name'] = $this->t('Project');
    $header['site'] = $this->t('Site ID');
    $header['type'] = $this->t('Type');
    $header['severity'] = $this->t('Severity');
    $header['resolved'] = $this->t('Resolved');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table row.
   */
  public function buildRow(EntityInterface $entity): array {
    $row['project_name'] = $entity->toLink(NULL, 'edit-form');
    $row['site'] = $this->fieldValue($entity, 'site');
    $row['type'] = $this->fieldValue($entity, 'type');
    $row['severity'] = $this->fieldValue($entity, 'severity');
    $row['resolved'] = $this->fieldValue($entity, 'resolved') === '1'
      ? $this->t('Yes')
      : $this->t('No');
    return $row + parent::buildRow($entity);
  }

}
