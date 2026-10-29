<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides shared helpers for Health Monitor entity lists.
 */
abstract class HealthMonitorEntityListBuilder extends EntityListBuilder {

  /**
   * Returns a field's plain string representation.
   */
  protected function fieldValue(EntityInterface $entity, string $fieldName): string {
    if (!$entity instanceof ContentEntityInterface || !$entity->hasField($fieldName)) {
      return '';
    }

    return $entity->get($fieldName)->getString();
  }

}
