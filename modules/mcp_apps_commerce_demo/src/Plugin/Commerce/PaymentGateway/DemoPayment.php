<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo\Plugin\Commerce\PaymentGateway;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\Manual;

/**
 * Reuses Commerce's manual payment implementation, with no live mode.
 */
#[CommercePaymentGateway(
  id: 'mcp_apps_demo',
  label: new TranslatableMarkup('MCP Apps simulated payment'),
  display_label: new TranslatableMarkup('Test payment · no real charge'),
  modes: [
    'test' => new TranslatableMarkup('Test'),
  ],
  payment_type: 'payment_manual',
  requires_billing_information: FALSE,
)]
final class DemoPayment extends Manual {}
