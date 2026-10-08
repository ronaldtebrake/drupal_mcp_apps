<?php

declare(strict_types=1);

namespace Drupal\mcp_apps;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Mcp\Schema\Extension\Apps\McpApps;

/**
 * Builds app resources from installed modules' self-contained bundles.
 */
final class AppResourceBuilder {

  public function __construct(private readonly ModuleExtensionList $modules) {}

  /**
   * Reads a module-owned HTML bundle into an uncached MCP App resource.
   */
  public function build(string $module, string $file, string $uri, array $ui = [], ?CacheableMetadata $cache = NULL): CacheableResourceContent {
    $root = realpath(DRUPAL_ROOT . '/' . $this->modules->getPath($module));
    $path = realpath($root . '/' . $file);
    if (!$root || !$path || !is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'html' || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !str_starts_with($uri, 'ui://')) {
      throw new \InvalidArgumentException('Use a module-owned bundle and a ui:// URI.');
    }
    $html = file_get_contents($path);
    if ($html === FALSE) {
      throw new \RuntimeException('The MCP App bundle is missing.');
    }
    return CacheableResourceContent::fromArray([
      'uri' => $uri,
      'mimeType' => McpApps::MIME_TYPE,
      'text' => $html,
      '_meta' => ['ui' => $ui],
    ], $cache ?? (new CacheableMetadata())->setCacheMaxAge(0));
  }

}
