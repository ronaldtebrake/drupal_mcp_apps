<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;

/**
 * Read demo basket.
 */
#[Tool(
  id: 'commerce_demo_cart',
  label: new TranslatableMarkup('Read demo basket'),
  description: new TranslatableMarkup("Read the authenticated customer's existing Commerce demo basket without creating an order."),
  operation: ToolOperation::Read,
  input_definitions: [],
  output_definitions: [
    'data' => new ContextDefinition('map', 'Commerce data'),
  ],
)]
final class CommerceCart extends CommerceToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('Demo basket ready.'), ['data' => $this->store->cart()]);
  }

}
