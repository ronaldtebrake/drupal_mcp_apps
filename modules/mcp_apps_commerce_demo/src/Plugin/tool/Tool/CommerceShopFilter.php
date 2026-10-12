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
 * Filter shopping app.
 */
#[Tool(
  id: 'commerce_shop_filter',
  label: new TranslatableMarkup('Filter shopping app'),
  description: new TranslatableMarkup('Filter the current shop or compare up to three products without another model call.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'session_id' => new InputDefinition(
      'string',
      'Session',
      'Account-bound shop session.'
    ),
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
    'compare' => new InputDefinition(
      'string',
      'Compare',
      'JSON list of up to three product IDs.',
      required: FALSE,
      default_value: '[]',
      constraints: ['Length' => ['max' => 100]]
    ),
    'view' => new InputDefinition(
      'string',
      'View',
      'Show products or the separate basket step.',
      required: FALSE,
      default_value: 'catalog',
      constraints: ['Choice' => ['choices' => ['catalog', 'basket']]]
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
final class CommerceShopFilter extends CommerceToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $compare = json_decode($values['compare'] ?? '[]', TRUE, flags: JSON_THROW_ON_ERROR);
    if (!is_array($compare)) {
      throw new \InvalidArgumentException('Use a product ID list.');
    }
    return $this->appResponse($this->shop->filter($values['session_id'], $values['query'] ?? '', $values['category'] ?? 'all', $values['budget'] ?? 0, $compare, $values['view'] ?? 'catalog'));
  }

}
