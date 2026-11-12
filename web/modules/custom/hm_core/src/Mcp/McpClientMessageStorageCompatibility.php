<?php

declare(strict_types=1);

namespace Drupal\hm_core\Mcp;

/**
 * Satisfies MCP 1.2's obsolete cron cleanup service dependency.
 *
 * MCP removed its stateful SSE transport and message storage, but version 1.2.3
 * still invokes the removed storage service from hook_cron(). The direct HTTP
 * transport used by Health Monitor has no client messages to clean up.
 */
final class McpClientMessageStorageCompatibility {

  /**
   * Completes the obsolete cleanup call without stateful storage.
   */
  public function cleanup(): void {
  }

}
