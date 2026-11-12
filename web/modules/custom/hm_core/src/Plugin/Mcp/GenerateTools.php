<?php

declare(strict_types=1);

namespace Drupal\hm_core\Plugin\Mcp;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hm_core\Report\ReportGenerationException;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\ServerFeatures\Tool;
use Drupal\mcp\ServerFeatures\ToolAnnotations;

/**
 * Generates versioned Health Monitor reports.
 */
#[Mcp(
  id: 'generate',
  name: new TranslatableMarkup('Health Monitor report tools'),
  description: new TranslatableMarkup('Generates client maintenance reports.'),
)]
final class GenerateTools extends HealthMonitorMcpPluginBase {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\mcp\ServerFeatures\Tool[]
   *   Tool definitions.
   */
  public function getTools(): array {
    return [
      new Tool(
        name: 'site_report',
        description: 'Generate and persist a new AI-written client report from one site’s deterministic open findings and effort estimate.',
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
        title: 'Generate site report',
        annotations: new ToolAnnotations(
          readOnlyHint: FALSE,
          idempotentHint: FALSE,
          destructiveHint: FALSE,
          openWorldHint: TRUE,
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
    if ($toolId !== 'site_report') {
      throw new \InvalidArgumentException('Tool not found.');
    }

    try {
      return $this->result($this->fleetTools->generateSiteReport($this->siteId($arguments)));
    }
    catch (ReportGenerationException $exception) {
      return $this->result(['error' => $exception->getMessage()], TRUE);
    }
  }

}
