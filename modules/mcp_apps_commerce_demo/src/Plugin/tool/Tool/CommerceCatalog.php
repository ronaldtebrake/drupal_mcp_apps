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
 * Find demo products.
 */
#[Tool(
  id: 'commerce_demo_catalog',
  label: new TranslatableMarkup('Find demo products'),
  description: new TranslatableMarkup('Return published demo products, variations, Commerce-resolved prices, availability and comparison specifications. Reusable independently of the MCP App.'),
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
  ],
  output_definitions: [
    'data' => new ContextDefinition('map', 'Commerce data'),
  ],
)]
final class CommerceCatalog extends CommerceToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('Demo catalog ready.'), [
      'data' => $this->store->catalog($values['query'], $values['category'], $values['budget']),
    ]);
  }

}
