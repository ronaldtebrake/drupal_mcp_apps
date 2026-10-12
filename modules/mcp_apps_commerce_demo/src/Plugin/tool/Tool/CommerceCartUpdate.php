<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Set demo basket quantity.
 */
#[Tool(
  id: 'commerce_demo_cart_update',
  label: new TranslatableMarkup('Set demo basket quantity'),
  description: new TranslatableMarkup('Set an absolute quantity for a published demo variation in your basket. Commerce owns cart events and pricing.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'variation_id' => new InputDefinition(
      'integer',
      'Variation',
      'Published demo variation ID.',
      constraints: ['Range' => ['min' => 1]]
    ),
    'quantity' => new InputDefinition(
      'integer',
      'Quantity',
      'Absolute whole quantity; 0 removes the item.',
      constraints: ['Range' => ['min' => 0, 'max' => 10]]
    ),
    'revision' => new InputDefinition(
      'string',
      'Revision',
      'Last server-issued basket revision.',
      required: FALSE,
      default_value: ''
    ),
  ],
  output_definitions: [
    'data' => new ContextDefinition('map', 'Commerce data'),
  ],
)]
final class CommerceCartUpdate extends CommerceToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('Demo basket updated.'), [
      'data' => $this->store->setQuantity($values['variation_id'], $values['quantity'], $values['revision']),
    ]);
  }

}
