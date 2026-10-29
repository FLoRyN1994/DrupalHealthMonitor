<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\Entity\EntityInterface;

/**
 * Builds the client administration list.
 */
final class ClientListBuilder extends HealthMonitorEntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The table header.
   */
  public function buildHeader(): array {
    $header['name'] = $this->t('Name');
    $header['contact_email'] = $this->t('Contact email');
    $header['retainer_tier'] = $this->t('Retainer tier');
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
    $row['contact_email'] = $this->fieldValue($entity, 'contact_email');
    $row['retainer_tier'] = $this->fieldValue($entity, 'retainer_tier');
    return $row + parent::buildRow($entity);
  }

}
