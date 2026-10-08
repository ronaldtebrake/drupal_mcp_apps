<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps_openui\Composer;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Transports attached preview assets through the host's MCP connection.
 */
#[Tool(
  id: 'component_composer_asset',
  label: new TranslatableMarkup('Read attached preview asset'),
  description: new TranslatableMarkup("Read a bounded chunk of an asset already attached to this account's validated preview. Only available to the MCP App."),
  operation: ToolOperation::Read,
  input_definitions: [
    'session_id' => new InputDefinition('string', 'Session', 'Account-bound composer session.'),
    'asset_id' => new InputDefinition('string', 'Asset', 'Attached asset hash.'),
    'offset' => new InputDefinition('integer', 'Offset', 'Byte offset.', required: FALSE, default_value: 0),
  ],
  output_definitions: ['data' => new ContextDefinition('map', 'Composer data')],
  meta: ['mcp' => ['ui' => ['resourceUri' => Composer::URI, 'visibility' => ['app']]]],
)]
final class ComponentComposerAsset extends ComposerToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->response($this->composer->asset($values['session_id'], $values['asset_id'], $values['offset']), 'Preview asset chunk.');
  }

}
