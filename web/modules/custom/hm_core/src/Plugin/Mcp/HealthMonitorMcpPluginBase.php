<?php

declare(strict_types=1);

namespace Drupal\hm_core\Plugin\Mcp;

use Drupal\hm_core\Mcp\FleetToolService;
use Drupal\mcp\Plugin\McpPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Health Monitor MCP tool groups.
 */
abstract class HealthMonitorMcpPluginBase extends McpPluginBase {

  /**
   * Deterministic fleet tool operations.
   */
  protected FleetToolService $fleetTools;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fleetTools = $container->get(FleetToolService::class);
    return $instance;
  }

  /**
   * Wraps structured data in an MCP text result.
   *
   * @param array<string, mixed> $data
   *   Structured tool data.
   * @param bool $isError
   *   Whether this is an error result.
   *
   * @return array<string, mixed>
   *   MCP tool result.
   */
  protected function result(array $data, bool $isError = FALSE): array {
    return [
      'content' => [
        [
          'type' => 'text',
          'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ],
      ],
      'structuredContent' => $data,
      'isError' => $isError,
    ];
  }

  /**
   * Returns a required positive site ID from tool arguments.
   */
  protected function siteId(mixed $arguments): int {
    if (!is_array($arguments) || !isset($arguments['site_id']) || !is_int($arguments['site_id'])) {
      throw new \InvalidArgumentException('site_id must be supplied as an integer.');
    }

    return $arguments['site_id'];
  }

}
