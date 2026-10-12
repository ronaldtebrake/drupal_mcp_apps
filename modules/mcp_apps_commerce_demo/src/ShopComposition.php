<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo;

/**
 * One component tree for the website, catalog previews and MCP App.
 */
final class ShopComposition {

  /**
   * Maps the catalog and basket to shared Drupal components.
   */
  public static function tree(array $catalog, array $cart, array $compare = [], array $receipt = [], string $view = 'catalog', bool $comparing = FALSE): array {
    $node = static fn($id, $props, $slots = []) => [
      'component' => 'mcp_apps_commerce_demo:' . $id,
      'props' => $props,
      'slots' => $slots,
    ];
    return [
      $node('shop', [
        'query' => $catalog['query'],
        'category' => $catalog['category'],
        'budget' => $catalog['budget'],
        'count' => count($catalog['products']),
        'view' => $view,
        'comparing' => $comparing,
      ], [
        'products' => $view === 'catalog' ? array_map(static fn($product) => $node('product', [
          'product' => $product,
          'compared' => in_array($product['id'], array_column($compare, 'id'), TRUE),
          'cart' => $cart,
          'show_details' => $comparing,
          'can_compare' => !$comparing,
        ]), $catalog['products']) : [],
        'comparison' => $view === 'catalog' && !$comparing && $compare ? [
          $node('comparison', ['products' => $compare]),
        ] : [],
        'summary' => $view === 'catalog' && $cart['count'] ? [
          $node('basket_summary', ['count' => $cart['count'], 'total' => $cart['total']]),
        ] : [],
        'basket' => $view === 'basket' && !$receipt ? [
          $node('basket', [
            'cart' => $cart,
            'can_checkout' => \Drupal::currentUser()->hasPermission('place mcp apps demo orders'),
          ]),
        ] : [],
        'receipt' => $view === 'basket' && $receipt ? [
          $node('receipt', ['receipt' => $receipt]),
        ] : [],
      ]),
    ];
  }

}
