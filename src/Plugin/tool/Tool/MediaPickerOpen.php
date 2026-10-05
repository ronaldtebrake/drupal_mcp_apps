<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Opens the Media picker through Drupal Tool API and the MCP bridge.
 */
#[Tool(
  id: 'media_picker_open',
  label: new TranslatableMarkup('Drupal Media picker'),
  description: new TranslatableMarkup('Update the hero for a demo article. Open by article_title or node_id, compare current and proposed images in an Olivero article preview, and explicitly confirm a draft revision. Opening is read-only.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'query' => new InputDefinition('string', 'Search', 'Media search text.', required: FALSE, default_value: '', constraints: ['Length' => ['max' => 200]]),
    'node_id' => new InputDefinition('integer', 'Article ID', 'The demo article node ID.', required: FALSE, default_value: 0),
    'article_title' => new InputDefinition('string', 'Article title', 'Find a demo article by title.', required: FALSE, default_value: '', constraints: ['Length' => ['max' => 200]]),
  ],
  meta: ['ui' => ['resourceUri' => MediaPicker::URI]],
)]
final class MediaPickerOpen extends MediaPickerToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->result((new MediaPicker())->open($values['query'], $values['node_id'], $values['article_title']));
  }

}
