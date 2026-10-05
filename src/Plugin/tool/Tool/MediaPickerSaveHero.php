<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Plugin\tool\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps\Mcp\HeroWorkflow;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Saves only the confirmed draft hero and article-specific alt text.
 */
#[Tool(
  id: 'media_picker_save_hero',
  label: new TranslatableMarkup('Save a draft article hero'),
  description: new TranslatableMarkup('After explicit confirmation in the Media picker, save a new unpublished revision with the selected image and article-specific alt text. Requires a current revision token; shared Media is unchanged.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'node_id' => new InputDefinition('integer', 'Article ID', 'The draft article node ID.'),
    'media_id' => new InputDefinition('integer', 'Media ID', 'The selected image Media ID.'),
    'alt' => new InputDefinition('string', 'Hero alt text', 'Article-specific alt text.', constraints: ['Length' => ['max' => 500]]),
    'revision' => new InputDefinition('string', 'Revision token', 'The revision token from the picker.'),
  ],
  meta: ['ui' => ['resourceUri' => MediaPicker::URI, 'visibility' => ['app']]],
)]
final class MediaPickerSaveHero extends MediaPickerToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->result((new HeroWorkflow())->save($values['node_id'], $values['media_id'], $values['alt'], $values['revision']));
  }

}
