<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Describes installed components for host-generated compositions.
 */
#[Tool(
  id: 'sdc_component_catalog',
  label: new TranslatableMarkup('Describe installed Drupal components'),
  description: new TranslatableMarkup('Discover existing Drupal Single Directory Components and their props, examples and named slots. Use provider to limit the catalog. The model composes these components using OpenUI; Drupal renders the original Twig and assets.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'provider' => new InputDefinition('string', 'Provider', 'Optional theme or module machine name.', required: FALSE, default_value: ''),
  ],
  output_definitions: ['data' => new ContextDefinition('map', 'Composer data')],
)]
final class SdcComponentCatalog extends ComposerToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->response([
      'data' => ['components' => $this->catalog->list($values['provider'] ?: NULL)],
    ], 'Drupal component catalog.');
  }

}
