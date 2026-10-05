<?php

declare(strict_types=1);

namespace Drupal\mcp_apps;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Registers native SDK capabilities so tool metadata is preserved.
 */
final class McpAppsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $container->getDefinition('mcp_server.server.factory')
      ->addMethodCall('addDiscovery', [__DIR__, ['Mcp']]);
  }

}
