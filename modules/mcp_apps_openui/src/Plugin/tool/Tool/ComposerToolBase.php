<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps_openui\ComponentCatalog;
use Drupal\mcp_apps_openui\Composer;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shares composer services, access checks, and presentation metadata.
 */
abstract class ComposerToolBase extends ToolBase {

  /**
   * The account-bound composer service.
   */
  protected Composer $composer;

  /**
   * The installed component catalog.
   */
  protected ComponentCatalog $catalog;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->composer = $container->get('mcp_apps_openui.composer');
    $instance->catalog = $container->get('mcp_apps_openui.catalog');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $access = AccessResult::allowedIf($account->isAuthenticated())->andIf(AccessResult::allowedIfHasPermission($account, 'use mcp apps composer'));
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Keeps presentation metadata separate from typed outputs.
   */
  protected function response(array $payload, string $message = 'Composer ready.'): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('@message', ['@message' => $message]), ['data' => $payload['data']], [
      'mcp' => ['ui' => $payload['ui'] ?? []],
    ]);
  }

}
