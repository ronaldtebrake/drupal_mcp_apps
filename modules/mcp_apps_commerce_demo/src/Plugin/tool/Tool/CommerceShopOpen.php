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
 * Open Commerce shopping app.
 */
#[Tool(
  id: 'commerce_shop_open',
  label: new TranslatableMarkup('Open Commerce shopping app'),
  description: new TranslatableMarkup('Open the coffee-gear MCP App. For help choosing the best product, use mode choose with relevant filters: product specifications appear immediately without Compare controls. Use browse for shopping, or auto to compare two or three matches. Budget is EUR; 0 means no limit. Checkout places simulated test orders.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'query' => new InputDefinition(
      'string',
      'Search',
      'Product title or description.',
      required: FALSE,
      default_value: '',
      constraints: ['Length' => ['max' => 100]]
    ),
    'category' => new InputDefinition(
      'string',
      'Category',
      'all, grinders, brewing or accessories.',
      required: FALSE,
      default_value: 'all',
      constraints: [
        'Choice' => [
          'choices' => [
            'all',
            'grinders',
            'brewing',
            'accessories',
          ],
        ],
      ]
    ),
    'budget' => new InputDefinition(
      'float',
      'Budget',
      'Maximum variation price in EUR; 0 means no limit.',
      required: FALSE,
      default_value: 0,
      constraints: ['Range' => ['min' => 0, 'max' => 1000]]
    ),
    'mode' => new InputDefinition(
      'string',
      'Mode',
      'choose for help selecting the best product; browse for shopping; auto compares two or three matches.',
      required: FALSE,
      default_value: 'auto',
      constraints: ['Choice' => ['choices' => ['auto', 'browse', 'choose']]]
    ),
  ],
  output_definitions: [
    'data' => new ContextDefinition('map', 'Commerce data'),
  ],
  meta: [
    'mcp' => ['ui' => ['resourceUri' => Shop::URI]],
  ],
)]
final class CommerceShopOpen extends CommerceToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->appResponse($this->shop->open($values['query'] ?? '', $values['category'] ?? 'all', $values['budget'] ?? 0, $values['mode'] ?? 'auto'));
  }

}
