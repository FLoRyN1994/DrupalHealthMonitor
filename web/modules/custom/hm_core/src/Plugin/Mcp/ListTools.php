<?php

declare(strict_types=1);

namespace Drupal\hm_core\Plugin\Mcp;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\ServerFeatures\Tool;
use Drupal\mcp\ServerFeatures\ToolAnnotations;

/**
 * Lists Health Monitor fleet data.
 */
#[Mcp(
  id: 'list',
  name: new TranslatableMarkup('Health Monitor list tools'),
  description: new TranslatableMarkup('Lists monitored Drupal fleet data.'),
)]
final class ListTools extends HealthMonitorMcpPluginBase {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\mcp\ServerFeatures\Tool[]
   *   Tool definitions.
   */
  public function getTools(): array {
    return [
      new Tool(
        name: 'sites',
        description: 'List monitored sites with health scores, open finding counts, security counts, and deterministic effort estimates.',
        inputSchema: ['type' => 'object', 'properties' => new \stdClass()],
        title: 'List monitored sites',
        annotations: new ToolAnnotations(
          readOnlyHint: TRUE,
          idempotentHint: TRUE,
          destructiveHint: FALSE,
          openWorldHint: FALSE,
        ),
      ),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   MCP tool result.
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    if ($toolId !== 'sites') {
      throw new \InvalidArgumentException('Tool not found.');
    }

    return $this->result(['sites' => $this->fleetTools->listSites()]);
  }

}
