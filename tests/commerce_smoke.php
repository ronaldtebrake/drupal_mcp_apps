<?php

/**
 * @file
 * Generates native Commerce fixtures for the SDK UI tests.
 */

\Drupal::service('account_switcher')->switchTo(\Drupal\user\Entity\User::load(1));
try {
  $shop = \Drupal::service('mcp_apps_commerce_demo.shop');
  $opened = $shop->open('', 'grinders', 150, 'browse');
  $session = $opened['data']['session_id'];
  $preview = \Drupal::service('mcp_apps_openui.composer')->preview($session, $opened['ui']['tree']);
  $assets = \Drupal::service('mcp_apps_openui.sessions')->get($session)['assets'];
  $products = $shop->invoke('commerce_demo_catalog', [])['structuredContent']['data']['products'];
  $compared = $shop->filter($session, '', 'grinders', 150, array_column(array_slice($products, 0, 2), 'id'));
  $comparedPreview = \Drupal::service('mcp_apps_openui.composer')->preview($session, $compared['ui']['tree']);
  $assets += \Drupal::service('mcp_apps_openui.sessions')->get($session)['assets'];
  $choosing = $shop->open('', 'grinders', 150, 'choose');
  $choosingPreview = \Drupal::service('mcp_apps_openui.composer')->preview($choosing['data']['session_id'], $choosing['ui']['tree']);
  $assets += \Drupal::service('mcp_apps_openui.sessions')->get($choosing['data']['session_id'])['assets'];
  // UI-only response fixtures. Real order persistence is covered by kernel tests.
  $variant = $products[0]['variants'][0];
  $cart = $opened;
  $cart['ui']['cart'] = ['id' => 99, 'items' => [['id' => 1, 'variation_id' => $variant['id'], 'title' => $products[0]['title'], 'quantity' => 1, 'unit_price' => $variant['price'], 'total' => $variant['price']]], 'count' => 1, 'total' => $variant['price'], 'revision' => 'reviewed-basket', 'state' => 'draft'];
  $catalog = $shop->invoke('commerce_demo_catalog', ['category' => 'grinders', 'budget' => 150])['structuredContent']['data'];
  $cart['ui']['tree'] = \Drupal\mcp_apps_commerce_demo\ShopComposition::tree($catalog, $cart['ui']['cart']);
  $cartPreview = \Drupal::service('mcp_apps_openui.composer')->preview($session, $cart['ui']['tree']);
  $basket = $cart;
  $basket['ui']['view'] = 'basket';
  $basket['ui']['tree'] = \Drupal\mcp_apps_commerce_demo\ShopComposition::tree($catalog, $cart['ui']['cart'], [], [], 'basket');
  $basketPreview = \Drupal::service('mcp_apps_openui.composer')->preview($session, $basket['ui']['tree']);
  $receipt = $opened;
  $receipt['ui']['receipt'] = ['order_id' => 99, 'number' => '99', 'total' => $variant['price'], 'state' => 'completed', 'payment' => 'simulated', 'url' => 'https://example.test/order/99'];
  $receipt['ui']['view'] = 'basket';
  $receipt['ui']['tree'] = \Drupal\mcp_apps_commerce_demo\ShopComposition::tree($catalog, $opened['ui']['cart'], [], $receipt['ui']['receipt'], 'basket');
  $receiptPreview = \Drupal::service('mcp_apps_openui.composer')->preview($session, $receipt['ui']['tree']);
  $assets += \Drupal::service('mcp_apps_openui.sessions')->get($session)['assets'];
  file_put_contents('/tmp/mcp-commerce-fixture.json', json_encode([
    'opened' => $opened, 'preview' => $preview, 'compared' => $compared, 'compared_preview' => $comparedPreview,
    'cart' => $cart, 'cart_preview' => $cartPreview, 'receipt' => $receipt, 'receipt_preview' => $receiptPreview,
    'basket' => $basket, 'basket_preview' => $basketPreview,
    'choosing' => $choosing, 'choosing_preview' => $choosingPreview,
    'bytes' => array_map(static fn($asset) => base64_encode($asset['bytes']), $assets),
  ], JSON_THROW_ON_ERROR));
  print json_encode(['products' => $opened['data']['products'], 'assets' => count($assets), 'html_bytes' => strlen($preview['ui']['html'])]) . PHP_EOL;
}
finally {
  \Drupal::service('account_switcher')->switchBack();
}
