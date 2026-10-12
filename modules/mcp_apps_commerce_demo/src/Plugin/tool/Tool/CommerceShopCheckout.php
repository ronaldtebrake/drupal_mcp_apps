<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps_commerce_demo\Shop;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Complete shopping app test order.
 */
#[Tool(
  id: 'commerce_shop_checkout',
  label: new TranslatableMarkup('Complete shopping app test order'),
  description: new TranslatableMarkup('Complete a reviewed test order through Commerce. Requires permission to place test orders.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'session_id' => new InputDefinition(
      'string',
      'Session',
      'Account-bound shop session.'
    ),
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
  meta: [
    'mcp' => [
      'ui' => [
        'resourceUri' => Shop::URI,
        'visibility' => ['app'],
      ],
    ],
  ],
)]
final class CommerceShopCheckout extends CommerceToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->appResponse($this->shop->checkout($values['session_id'], $values['order_id'], $values['revision']));
  }

}
