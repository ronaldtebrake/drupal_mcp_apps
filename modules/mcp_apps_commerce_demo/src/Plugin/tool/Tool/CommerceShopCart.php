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
 * Update shopping app basket.
 */
#[Tool(
  id: 'commerce_shop_cart',
  label: new TranslatableMarkup('Update shopping app basket'),
  description: new TranslatableMarkup('Update a basket quantity through the reusable Commerce Tool API operation.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'session_id' => new InputDefinition(
      'string',
      'Session',
      'Account-bound shop session.'
    ),
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
  meta: [
    'mcp' => [
      'ui' => [
        'resourceUri' => Shop::URI,
        'visibility' => ['app'],
      ],
    ],
  ],
)]
final class CommerceShopCart extends CommerceToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->appResponse($this->shop->cart($values['session_id'], $values['variation_id'], $values['quantity'], $values['revision']));
  }

}
