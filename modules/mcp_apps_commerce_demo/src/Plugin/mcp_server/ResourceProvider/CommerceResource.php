<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo\Plugin\mcp_server\ResourceProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps\AppResourceBuilder;
use Drupal\mcp_apps_commerce_demo\Shop;
use Drupal\mcp_server\Attribute\ResourceProvider;
use Drupal\mcp_server\Plugin\ResourceProviderBase;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Mcp\Schema\Extension\Apps\McpApps;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Registers the shopping app through the native MCP Server resource API.
 */
#[ResourceProvider(id: 'mcp_apps_commerce', label: new TranslatableMarkup('Commerce shop'), description: new TranslatableMarkup('Shared Drupal shopping components.'))]
final class CommerceResource extends ResourceProviderBase {
  /**
   * The shared service.
   *
   * @var \Drupal\mcp_apps\AppResourceBuilder
   */
  /**
   * The shared service.
   *
   * @var \Drupal\mcp_apps_commerce_demo\AppResourceBuilder
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
      'uri' => Shop::URI,
      'name' => 'Fieldwork Supply',
      'mimeType' => McpApps::MIME_TYPE,
      'meta' => ['ui' => McpApps::resourceMarker()],
    ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(string $uri, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($uri === Shop::URI && $account->isAuthenticated())
      ->andIf(AccessResult::allowedIfHasPermission($account, 'use mcp apps commerce demo'))
      ->andIf(AccessResult::allowedIfHasPermission($account, 'use mcp apps composer'));
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceContent(string $uri): ?CacheableResourceContent {
    return $uri === Shop::URI ? $this->resources->build('mcp_apps_commerce_demo', 'dist/shop.html', $uri, [
      'prefersBorder' => FALSE,
      'csp' => [
        'connectDomains' => [],
        'resourceDomains' => ['data:', 'blob:'],
        'frameDomains' => ['blob:'],
      ],
    ]) : NULL;
  }

}
