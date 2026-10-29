<?php

declare(strict_types=1);

namespace Drupal\hm_core\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the add and edit form for Health Monitor entities.
 */
final class HealthMonitorEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public function save(array $form, FormStateInterface $formState) {
    $status = parent::save($form, $formState);
    $this->messenger()->addStatus($this->t('%label has been saved.', [
      '%label' => $this->entity->label() ?? $this->entity->getEntityType()->getLabel(),
    ]));
    $formState->setRedirectUrl($this->entity->toUrl('collection'));

    return $status;
  }

}
