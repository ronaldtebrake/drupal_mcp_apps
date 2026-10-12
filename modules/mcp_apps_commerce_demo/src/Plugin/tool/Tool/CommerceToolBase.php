<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps_commerce_demo\Shop;
use Drupal\mcp_apps_commerce_demo\Store;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shares services and permission checks for the optional shopping demo.
 */
abstract class CommerceToolBase extends ToolBase {
  /**
   * The shared service.
   *
   * @var \Drupal\mcp_apps_commerce_demo\Store
   */
  protected Store $store;
  /**
   * The shared service.
   *
   * @var \Drupal\mcp_apps_commerce_demo\Shop
   */
  protected Shop $shop;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->store = $container->get('mcp_apps_commerce_demo.store');
    $instance->shop = $container->get('mcp_apps_commerce_demo.shop');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIf($account->isAuthenticated())->andIf(AccessResult::allowedIfHasPermission($account, 'use mcp apps commerce demo'));
    if (str_starts_with($this->getPluginId(), 'commerce_shop_')) {
      $access = $access->andIf(AccessResult::allowedIfHasPermission($account, 'use mcp apps composer'));
    }
    if (str_ends_with($this->getPluginId(), '_checkout')) {
      $access = $access->andIf(AccessResult::allowedIfHasPermission($account, 'place mcp apps demo orders'));
    }
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Keeps presentation metadata separate from tool outputs.
   */
  protected function appResponse(array $payload): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('Commerce view updated. Test store; no real charges.'), ['data' => $payload['data']], ['mcp' => ['ui' => $payload['ui']]]);
  }

}
