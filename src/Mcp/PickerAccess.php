<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Mcp;

use Drupal\mcp_server\Exception\McpAuthorizationDeniedException;

/**
 * Permission boundary shared by SDK callbacks and article capabilities.
 */
final class PickerAccess {

  public const OPEN = 'access mcp media picker';
  public const UPDATE = 'update mcp article hero';

  /**
   * Checks permissions without bypassing entity or field access.
   */
  public static function allowed(bool $update = FALSE): bool {
    $account = \Drupal::currentUser();
    return $account->hasPermission(self::OPEN) && (!$update || $account->hasPermission(self::UPDATE));
  }

  /**
   * Rejects unauthorized calls before loading content or changing storage.
   */
  public static function requireAccess(bool $update = FALSE): void {
    if (!self::allowed($update)) {
      throw new McpAuthorizationDeniedException('Access denied: the MCP Media picker permission is required' . ($update ? ', together with the article hero update permission.' : '.'));
    }
  }

}
