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
 * Opens an interactive preview of an OpenUI component composition.
 */
#[Tool(
  id: 'component_composer_open',
  label: new TranslatableMarkup('Open Drupal component composer'),
  description: new TranslatableMarkup('Open an MCP App composing existing Drupal components using OpenUI. Read sdc_component_catalog first. Supply OpenUI program: root = Page([DrupalComponent(componentId, propsObject, namedSlotsObject)]). Nested slots contain DrupalComponent lists. Opening and editing only preview; no content is saved.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'title' => new InputDefinition('string', 'Title', 'Composition title.', required: FALSE, default_value: 'Component preview', constraints: ['Length' => ['max' => 200]]),
    'theme' => new InputDefinition('string', 'Theme', 'Installed theme for preview; empty uses the configured preview theme or site default.', required: FALSE, default_value: ''),
    'program' => new InputDefinition('string', 'OpenUI program', 'Page and DrupalComponent composition. Omit to open the optional demo fixture.', required: FALSE, default_value: '', constraints: ['Length' => ['max' => 65536]]),
  ],
  output_definitions: ['data' => new ContextDefinition('map', 'Composer data')],
  meta: ['mcp' => ['ui' => ['resourceUri' => Composer::URI]]],
)]
final class ComponentComposerOpen extends ComposerToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->response($this->composer->open($values['title'], $values['theme'], $values['program']));
  }

}
