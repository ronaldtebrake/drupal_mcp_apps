<?php

namespace Drupal\mcp_apps_test\Plugin\mcp_server\ResourceProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_server\Attribute\ResourceProvider;
use Drupal\mcp_server\Plugin\ResourceProviderBase;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Mcp\Schema\Extension\Apps\McpApps;

/**
 * Defines the AppResource plugin.
 */
#[ResourceProvider(id: 'mcp_apps_test', label: new TranslatableMarkup('First test app'), description: new TranslatableMarkup('Independent app fixture.'))]
final class AppResource extends ResourceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [
      [
        'uri' => 'ui://mcp_apps_test/app',
        'name' => 'First app',
        'mimeType' => McpApps::MIME_TYPE,
        'meta' => ['ui' => McpApps::resourceMarker()],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(string $uri, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($uri === 'ui://mcp_apps_test/app')->andIf(AccessResult::allowedIfHasPermission($account, 'use test apps'));
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceContent(string $uri): ?CacheableResourceContent {
    if ($uri !== 'ui://mcp_apps_test/app') {
      return NULL;
    }
    $content = \Drupal::service('mcp_apps.resource_builder')->build('mcp_apps_test', 'app.html', $uri, [
      'csp' => ['connectDomains' => [], 'resourceDomains' => []],
    ], (new CacheableMetadata())->setCacheContexts(['user'])->setCacheMaxAge(0));
    $raw = $content->toResourceContents();
    $raw['text'] .= 'account:' . \Drupal::currentUser()->id();
    return CacheableResourceContent::fromArray($raw, $content->metadata);
  }

}
