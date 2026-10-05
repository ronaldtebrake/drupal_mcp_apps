<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Plugin\mcp_server\ResourceProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Drupal\mcp_apps\Mcp\PickerAccess;
use Drupal\mcp_server\Attribute\ResourceProvider;
use Drupal\mcp_server\Plugin\ResourceProviderBase;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Mcp\Schema\Extension\Apps\McpApps;

/**
 * Serves account-specific app HTML through MCP Server's resource API.
 */
#[ResourceProvider(
  id: 'mcp_apps_media_picker',
  label: new TranslatableMarkup('MCP Media picker app'),
  description: new TranslatableMarkup('The Media picker HTML app resource.'),
)]
final class MediaPickerResource extends ResourceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [
      [
        'uri' => MediaPicker::URI,
        'name' => 'drupal-media-picker',
        'mimeType' => McpApps::MIME_TYPE,
        'meta' => ['ui' => McpApps::resourceMarker()],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(string $uri, AccountInterface $account): AccessResultInterface {
    return $uri === MediaPicker::URI ? AccessResult::allowedIfHasPermission($account, PickerAccess::OPEN) : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceContent(string $uri): ?CacheableResourceContent {
    if ($uri !== MediaPicker::URI) {
      return NULL;
    }
    // HTML embeds current-account images; never share or persist that payload.
    return CacheableResourceContent::fromArray((new MediaPicker())->resource()->jsonSerialize(), (new CacheableMetadata())->setCacheMaxAge(0));
  }

}
