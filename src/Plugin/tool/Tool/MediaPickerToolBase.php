<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps\Mcp\PickerAccess;
use Drupal\tool\ExecutableResult;
use Drupal\tool\FailureCategory;
use Drupal\tool\Tool\ToolBase;
use Mcp\Schema\Result\CallToolResult;

/**
 * Shares permission checks and result conversion for the two Tool API tools.
 */
abstract class MediaPickerToolBase extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $permissions = [PickerAccess::OPEN];
    if ($this->getPluginId() === 'media_picker_save_hero') {
      $permissions[] = PickerAccess::UPDATE;
    }
    $access = AccessResult::allowedIfHasPermissions($account, $permissions);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Converts shared workflow data without mixing image bytes into outputs.
   */
  protected function result(CallToolResult $result): ExecutableResult {
    $message = new TranslatableMarkup('@message', ['@message' => $result->content[0]->text]);
    if ($result->isError) {
      return ExecutableResult::failure($message, category: FailureCategory::Runtime);
    }
    return ExecutableResult::success($message, $result->structuredContent ?? [], $result->meta ?? []);
  }

}
