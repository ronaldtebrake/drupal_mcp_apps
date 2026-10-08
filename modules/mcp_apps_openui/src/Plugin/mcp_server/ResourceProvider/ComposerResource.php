<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui\Plugin\mcp_server\ResourceProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps\AppResourceBuilder;
use Drupal\mcp_apps_openui\Composer;
use Drupal\mcp_server\Attribute\ResourceProvider;
use Drupal\mcp_server\Plugin\ResourceProviderBase;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Mcp\Schema\Extension\Apps\McpApps;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Registers the bundled OpenUI interface through MCP Server's resource API.
 */
#[ResourceProvider(id: 'mcp_apps_composer', label: new TranslatableMarkup('Drupal component composer'), description: new TranslatableMarkup('OpenUI composition with Drupal components.'))]
final class ComposerResource extends ResourceProviderBase {

  /**
   * The module-owned app resource builder.
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
    return [
      [
        'uri' => Composer::URI,
        'name' => 'Drupal component composer',
        'mimeType' => McpApps::MIME_TYPE,
        'meta' => ['ui' => McpApps::resourceMarker()],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(string $uri, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($uri === Composer::URI && $account->isAuthenticated())->andIf(AccessResult::allowedIfHasPermission($account, 'use mcp apps composer'));
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceContent(string $uri): ?CacheableResourceContent {
    return $uri === Composer::URI ? $this->resources->build('mcp_apps_openui', 'dist/composer.html', $uri, [
      'prefersBorder' => FALSE,
      'csp' => ['connectDomains' => [], 'resourceDomains' => ['blob:', 'data:'], 'frameDomains' => ['blob:']],
    ]) : NULL;
  }

}
