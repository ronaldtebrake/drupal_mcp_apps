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
 * Validates and renders a composition without saving Drupal content.
 */
#[Tool(
  id: 'component_composer_preview',
  label: new TranslatableMarkup('Preview Drupal composition'),
  description: new TranslatableMarkup('Validate a plain component tree and render the actual Drupal output. Only available to the MCP App.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'session_id' => new InputDefinition('string', 'Session', 'Account-bound composer session.'),
    'composition' => new InputDefinition('string', 'Composition', 'JSON array of component, props, and named slots.', constraints: ['Length' => ['max' => 65536]]),
  ],
  output_definitions: ['data' => new ContextDefinition('map', 'Composer data')],
  meta: ['mcp' => ['ui' => ['resourceUri' => Composer::URI, 'visibility' => ['app']]]],
)]
final class ComponentComposerPreview extends ComposerToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    try {
      $tree = json_decode($values['composition'], TRUE, 64, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new \InvalidArgumentException('Composition must be valid JSON.', previous: $e);
    }
    if (!is_array($tree)) {
      throw new \InvalidArgumentException('Composition must be a JSON component list.');
    }
    return $this->response($this->composer->preview($values['session_id'], $tree), 'Drupal preview updated.');
  }

}
