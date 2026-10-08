<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_demo\Plugin\mcp_server\ResourceProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps\AppResourceBuilder;
use Drupal\mcp_apps_activity_demo\Dashboard;
use Drupal\mcp_server\Attribute\ResourceProvider;
use Drupal\mcp_server\Plugin\ResourceProviderBase;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Mcp\Schema\Extension\Apps\McpApps;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Registers the activity app through MCP Server's native resource API.
 */
#[ResourceProvider(id: 'mcp_apps_activity', label: new TranslatableMarkup('Editorial pulse'), description: new TranslatableMarkup('Native Drupal charts and editorial activity.'))]
final class ActivityResource extends ResourceProviderBase {

  /**
   * The module-owned resource builder.
   */
  private AppResourceBuilder $resources;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->resources = $container->get('mcp_apps.resource_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [[
      'uri' => Dashboard::URI,
      'name' => 'Editorial pulse',
      'mimeType' => McpApps::MIME_TYPE,
      'meta' => ['ui' => McpApps::resourceMarker()],
    ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(string $uri, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($uri === Dashboard::URI && $account->isAuthenticated())->andIf(AccessResult::allowedIfHasPermission($account, 'use mcp apps composer'));
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceContent(string $uri): ?CacheableResourceContent {
    return $uri === Dashboard::URI ? $this->resources->build('mcp_apps_activity_demo', 'dist/activity.html', $uri, [
      'prefersBorder' => FALSE,
      'csp' => ['connectDomains' => [], 'resourceDomains' => ['data:', 'blob:'], 'frameDomains' => ['blob:']],
    ]) : NULL;
  }

}
