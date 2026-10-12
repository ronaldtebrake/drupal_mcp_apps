<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\mcp_apps_openui\Composer;
use Drupal\mcp_apps_openui\ComposerSessions;

/**
 * Maps reusable Commerce tools to a shared native SDC shopping interface.
 */
final class Shop {
  public const URI = 'ui://drupal/commerce-shop';

  public function __construct(
    private readonly PluginManagerInterface $tools,
    private readonly Composer $composer,
    private readonly ComposerSessions $sessions,
    private readonly ConfigFactoryInterface $config,
  ) {}

  /**
   * Opens an account-bound shop session.
   */
  public function open(string $query = '', string $category = 'all', float $budget = 0, string $mode = 'auto'): array {
    if (!in_array($mode, ['auto', 'browse', 'choose'], TRUE)) {
      throw new \InvalidArgumentException('Choose auto, browse or choose mode.');
    }
    $opened = $this->composer->open('Fieldwork Supply', $this->config->get('system.theme')->get('default'), 'Page([])');
    $id = $opened['data']['session_id'];
    $state = $this->sessions->get($id);
    $state['shop'] = [
      'query' => $query,
      'category' => $category,
      'budget' => $budget,
      'compare' => [],
      'receipt' => [],
      'view' => 'catalog',
      'mode' => $mode,
    ];
    $this->sessions->put($id, $state);
    return $this->view($id);
  }

  /**
   * Updates the shop filters and comparison.
   */
  public function filter(string $id, string $query, string $category, float $budget, array $compare, string $view = 'catalog'): array {
    if (!in_array($view, ['catalog', 'basket'], TRUE)) {
      throw new \InvalidArgumentException('Choose the catalog or basket view.');
    }
    if (count($compare) > 3 || !array_is_list($compare) || array_filter($compare, static fn($item) => !is_int($item) || $item < 1)) {
      throw new \InvalidArgumentException('Compare up to three product IDs.');
    }
    return $this->sessions->synchronized($id, function ($state) use ($id, $query, $category, $budget, $compare, $view) {
      $this->invoke('commerce_demo_catalog', [
        'query' => $query,
        'category' => $category,
        'budget' => $budget,
      ]);
      $available = array_column($this->invoke('commerce_demo_catalog', [])['structuredContent']['data']['products'], 'id');
      if (array_diff($compare, $available)) {
        throw new \InvalidArgumentException('A selected comparison product is no longer accessible.');
      }
      $state['shop'] = array_replace($state['shop'], [
        'query' => $query,
        'category' => $category,
        'budget' => $budget,
        'compare' => array_values(array_unique($compare)),
        'view' => $view,
      ]);
      $this->sessions->put($id, $state);
      return $this->view($id);
    });
  }

  /**
   * Updates the current shopper basket.
   */
  public function cart(string $id, int $variation, int $quantity, string $revision): array {
    $state = $this->shopState($id);
    $this->invoke('commerce_demo_cart_update', [
      'variation_id' => $variation,
      'quantity' => $quantity,
      'revision' => $revision,
    ]);
    $state['shop']['receipt'] = [];
    $this->sessions->put($id, $state);
    return $this->view($id);
  }

  /**
   * Completes a reviewed test order.
   */
  public function checkout(string $id, int $order, string $revision): array {
    $state = $this->shopState($id);
    $state['shop']['receipt'] = $this->invoke('commerce_demo_checkout', [
      'order_id' => $order,
      'revision' => $revision,
    ])['structuredContent']['data'];
    $state['shop']['view'] = 'basket';
    $this->sessions->put($id, $state);
    return $this->view($id);
  }

  /**
   * Builds the shared component view from Tool API results.
   */
  public function view(string $id): array {
    $state = $this->shopState($id);
    $filters = array_intersect_key($state['shop'], array_flip(['query', 'category', 'budget']));
    $catalog = $this->invoke('commerce_demo_catalog', $filters)['structuredContent']['data'];
    $cart = $this->invoke('commerce_demo_cart', [])['structuredContent']['data'];
    $mode = $state['shop']['mode'] ?? 'auto';
    $comparing = $mode === 'choose' || ($mode === 'auto' && count($catalog['products']) >= 2 && count($catalog['products']) <= 3);
    $comparison = [];
    if ($state['shop']['compare']) {
      $all = $this->invoke('commerce_demo_catalog', [])['structuredContent']['data']['products'];
      $comparison = array_values(array_filter($all, static fn($product) => in_array($product['id'], $state['shop']['compare'], TRUE)));
      if (count($comparison) !== count($state['shop']['compare'])) {
        throw new \InvalidArgumentException('A selected comparison product is no longer accessible.');
      }
    }
    return [
      'data' => [
        'session_id' => $id,
        'products' => count($catalog['products']),
        'cart_count' => $cart['count'],
        'cart_total' => $cart['total'],
        'test_mode' => TRUE,
      ] + $filters,
      'ui' => [
        'session_id' => $id,
        'tree' => ShopComposition::tree($catalog, $cart, $comparison, $state['shop']['receipt'], $state['shop']['view'] ?? 'catalog', $comparing),
        'mode' => $mode,
        'view' => $state['shop']['view'] ?? 'catalog',
        'filters' => $filters,
        'compare' => $state['shop']['compare'],
        'cart' => $cart,
        'receipt' => $state['shop']['receipt'],
        'theme' => $state['theme'],
        'website_url' => Url::fromRoute('mcp_apps_commerce_demo.shop', [], [
          'absolute' => TRUE,
          'query' => $filters + ['mode' => $mode],
        ])->toString(),
      ],
    ];
  }

  /**
   * Validates and executes an allowlisted Tool API operation.
   */
  public function invoke(string $name, array $inputs): array {
    if (!in_array($name, [
      'commerce_demo_catalog',
      'commerce_demo_cart',
      'commerce_demo_cart_update',
      'commerce_demo_checkout',
      'commerce_shop_open',
      'commerce_shop_filter',
      'commerce_shop_cart',
      'commerce_shop_checkout',
      'component_composer_preview',
      'component_composer_asset',
    ], TRUE) || !$this->tools->hasDefinition($name)) {
      throw new \InvalidArgumentException('Unsupported shop operation.');
    }
    $tool = $this->tools->createInstance($name);
    if (array_diff(array_keys($inputs), array_keys($tool->getInputDefinitions()))) {
      throw new \InvalidArgumentException('Unknown shop inputs.');
    }
    foreach ($inputs as $name => $value) {
      $tool->setInputValue($name, $value);
    }
    if ($tool->validateInputs()->count() || !$tool->access()) {
      throw new \InvalidArgumentException('Access denied or invalid shop inputs.');
    }
    $result = $tool->execute()->getResult();
    if (!$result->isSuccess()) {
      throw new \InvalidArgumentException((string) $result->getMessage());
    }
    return [
      'structuredContent' => $result->getContextValues(),
      '_meta' => $result->getMeta()['mcp'] ?? [],
      'content' => [
        [
          'type' => 'text',
          'text' => (string) $result->getMessage(),
        ],
      ],
    ];
  }

  /**
   * Requires an existing account-owned shop session.
   */
  private function shopState(string $id): array {
    $state = $this->sessions->get($id);
    if (!isset($state['shop'])) {
      throw new \InvalidArgumentException('Open a Commerce shop session first.');
    }
    return $state;
  }

}
