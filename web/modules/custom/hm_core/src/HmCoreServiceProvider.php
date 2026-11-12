<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\hm_core\Mcp\McpClientMessageStorageCompatibility;

/**
 * Registers compatibility services required by Health Monitor dependencies.
 */
final class HmCoreServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    if (!$container->hasDefinition('mcp.client_message.storage')) {
      $container->register(
        'mcp.client_message.storage',
        McpClientMessageStorageCompatibility::class,
      );
    }
  }

}
