<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Unit\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hm_core\Hook\HmCoreHooks;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the module hooks.
 */
#[CoversClass(HmCoreHooks::class)]
#[Group('hm_core')]
final class HmCoreHooksTest extends UnitTestCase {

  /**
   * Tests that help text is limited to the module help page.
   */
  public function testHelp(): void {
    $hooks = new HmCoreHooks(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(MessengerInterface::class),
      $this->createMock(AccountProxyInterface::class),
    );
    $hooks->setStringTranslation($this->getStringTranslationStub());
    $routeMatch = $this->createMock(RouteMatchInterface::class);

    self::assertNull($hooks->help('system.admin', $routeMatch));
    self::assertSame(
      '<p>Provides fleet monitoring, analysis, and reporting for Drupal sites.</p>',
      $hooks->help('help.page.hm_core', $routeMatch),
    );
  }

  /**
   * Tests the accurate pending-account confirmation callback.
   */
  public function testRegistrationConfirmation(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['register', 'visitors_admin_approval'],
      ['notify.register_pending_approval', FALSE],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('user.settings')->willReturn($settings);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())
      ->method('deleteByType')
      ->with(MessengerInterface::TYPE_STATUS);
    $messenger->expects(self::once())
      ->method('addStatus')
      ->with(self::callback(
        static fn (mixed $message): bool => str_contains((string) $message, 'pending administrator approval'),
      ));

    $hooks = new HmCoreHooks($configFactory, $messenger, $this->currentUser(FALSE));
    $hooks->setStringTranslation($this->getStringTranslationStub());
    $form = ['actions' => ['submit' => ['#submit' => []]]];
    $formState = $this->createMock(FormStateInterface::class);
    $account = $this->createMock(UserInterface::class);
    $account->expects(self::once())
      ->method('hasRole')
      ->with('health_monitor_operator')
      ->willReturn(FALSE);
    $account->expects(self::once())
      ->method('addRole')
      ->with('health_monitor_operator');
    $account->expects(self::once())->method('save');
    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($account);
    $formState->method('getFormObject')->willReturn($formObject);

    $hooks->userRegisterFormAlter($form, $formState);
    self::assertSame(
      [$hooks, 'replaceRegistrationConfirmation'],
      $form['actions']['submit']['#submit'][0],
    );
    $hooks->replaceRegistrationConfirmation($form, $formState);
  }

  /**
   * Tests that administrator-created accounts are left untouched.
   */
  public function testAdministratorCreatedAccountIsNotAltered(): void {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects(self::never())->method('get');
    $hooks = new HmCoreHooks(
      $configFactory,
      $this->createMock(MessengerInterface::class),
      $this->currentUser(TRUE),
    );
    $form = ['actions' => ['submit' => ['#submit' => []]]];

    $hooks->userRegisterFormAlter($form, $this->createMock(FormStateInterface::class));

    self::assertSame([], $form['actions']['submit']['#submit']);
  }

  /**
   * Creates a current-user stub.
   */
  private function currentUser(bool $authenticated): AccountProxyInterface {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('isAuthenticated')->willReturn($authenticated);
    return $currentUser;
  }

}
