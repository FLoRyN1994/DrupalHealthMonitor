<?php

declare(strict_types=1);

namespace Drupal\hm_core\Plugin\Mcp;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\ServerFeatures\Tool;
use Drupal\mcp\ServerFeatures\ToolAnnotations;

/**
 * Reads detailed Health Monitor findings.
 */
#[Mcp(
  id: 'get',
  name: new TranslatableMarkup('Health Monitor finding tools'),
  description: new TranslatableMarkup('Reads deterministic fleet findings.'),
)]
final class FindingTools extends HealthMonitorMcpPluginBase {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\mcp\ServerFeatures\Tool[]
   *   Tool definitions.
   */
  public function getTools(): array {
    $annotations = new ToolAnnotations(
      readOnlyHint: TRUE,
      idempotentHint: TRUE,
      destructiveHint: FALSE,
      openWorldHint: FALSE,
    );

    return [
      new Tool(
        name: 'site_findings',
        description: 'Get every open finding and the deterministic effort estimate for one monitored site.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'site_id' => [
              'type' => 'integer',
              'minimum' => 1,
              'description' => 'The monitored Site entity ID.',
            ],
          ],
          'required' => ['site_id'],
          'additionalProperties' => FALSE,
        ],
        title: 'Get site findings',
        annotations: $annotations,
      ),
      new Tool(
        name: 'security_updates',
        description: 'Get all open security updates across the fleet and their total deterministic effort estimate.',
        inputSchema: ['type' => 'object', 'properties' => new \stdClass()],
        title: 'Get fleet security updates',
        annotations: $annotations,
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
    return match ($toolId) {
      'site_findings' => $this->result($this->fleetTools->getSiteFindings($this->siteId($arguments))),
      'security_updates' => $this->result($this->fleetTools->getSecurityUpdates()),
      default => throw new \InvalidArgumentException('Tool not found.'),
    };
  }

}
