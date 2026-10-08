<?php

namespace Drupal\mcp_apps_other_test\Plugin\mcp_server\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_server\Attribute\Tool;
use Drupal\mcp_server\Plugin\ToolPluginBase;
use Mcp\Server\ClientGateway;

/**
 * Defines the OpenApp plugin.
 */
#[Tool(id: 'mcp_apps_other_test_open', label: new TranslatableMarkup('Second app'), description: new TranslatableMarkup('Open independent test app.'), readOnly: TRUE, destructive: FALSE, meta: [
  'ui' => ['resourceUri' => 'ui://mcp_apps_other_test/app'],
])]
final class OpenApp extends ToolPluginBase {

  /**
   * Provides default configuration.
   */
  protected function defaultConfiguration(): array {
    return ['enabled' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments, ClientGateway $gateway): mixed {
    return ['success' => TRUE, 'message' => 'Second app'];
  }

}
