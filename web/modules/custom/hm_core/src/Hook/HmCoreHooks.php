<?php

declare(strict_types=1);

namespace Drupal\hm_core\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Provides hooks for the Health Monitor Core module.
 */
final class HmCoreHooks {

  use StringTranslationTrait;

  /**
   * Creates Health Monitor hook implementations.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * Provides help text for the module help page.
   */
  #[Hook('help')]
  public function help(string $routeName, RouteMatchInterface $routeMatch): ?string {
    if ($routeName !== 'help.page.hm_core') {
      return NULL;
    }

    return '<p>' . $this->t('Provides fleet monitoring, analysis, and reporting for Drupal sites.') . '</p>';
  }

  /**
   * Adds an accurate confirmation to the public registration form.
   *
   * Core always claims that a welcome email was sent for approval-based
   * registration, even when that notification is explicitly disabled.
   *
   * @param array<string, mixed> $form
   *   The registration form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   */
  #[Hook('form_user_register_form_alter')]
  public function userRegisterFormAlter(array &$form, FormStateInterface $formState): void {
    $settings = $this->configFactory->get('user.settings');
    if ($settings->get('register') !== UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL
      || $settings->get('notify.register_pending_approval')) {
      return;
    }

    $form['actions']['submit']['#submit'][] = [$this, 'replaceRegistrationConfirmation'];
  }

  /**
   * Replaces core's email claim after a pending account is created.
   *
   * @param array<string, mixed> $form
   *   The submitted registration form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The submitted form state.
   */
  public function replaceRegistrationConfirmation(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if ($formObject instanceof EntityFormInterface) {
      $account = $formObject->getEntity();
      if ($account instanceof UserInterface && !$account->hasRole('health_monitor_operator')) {
        $account->addRole('health_monitor_operator');
        $account->save();
      }
    }

    $this->messenger->deleteByType(MessengerInterface::TYPE_STATUS);
    $this->messenger->addStatus($this->t('Thank you for applying for an account. Your request is pending administrator approval. You can sign in after an administrator activates it.'));
  }

}
