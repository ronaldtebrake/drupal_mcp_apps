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
 * Place test Commerce order.
 */
#[Tool(
  id: 'commerce_demo_checkout',
  label: new TranslatableMarkup('Place test Commerce order'),
  description: new TranslatableMarkup('Place your reviewed demo order with simulated payment. Never charges money; rejects live gateways and stale totals.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'order_id' => new InputDefinition(
      'integer',
      'Basket',
      'Your demo order ID.',
      constraints: ['Range' => ['min' => 1]]
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
final class CommerceCheckout extends CommerceToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('Test order placed. No real charge.'), [
      'data' => $this->store->checkout($values['order_id'], $values['revision']),
    ]);
  }

}
