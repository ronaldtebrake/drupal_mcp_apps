<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo;

use Drupal\commerce_price\Calculator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\commerce\Context;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderRefreshInterface;
use Drupal\commerce_order\AvailabilityManagerInterface;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_price\CurrencyFormatter;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * A demo-store adapter around Commerce's pricing, cart and payment services.
 */
final class Store {

  public const BUNDLE = 'mcp_apps_demo';

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly CartProviderInterface $carts,
    private readonly CartManagerInterface $manager,
    private readonly ChainPriceResolverInterface $prices,
    private readonly CurrencyFormatter $formatter,
    private readonly AvailabilityManagerInterface $availability,
    private readonly OrderRefreshInterface $refresh,
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    private readonly ConfigFactoryInterface $config,
    private readonly ModuleExtensionList $modules,
  ) {}

  /**
   * Loads only the explicitly configured showcase store.
   */
  public function store(): StoreInterface {
    $stores = $this->entities->getStorage('commerce_store')->loadByProperties([
      'uuid' => $this->config->get('mcp_apps_commerce_demo.settings')->get('store_uuid'),
    ]);
    $store = reset($stores);
    if (!$store instanceof StoreInterface) {
      throw new \InvalidArgumentException('Run the explicit Commerce demo seed first.');
    }
    return $store;
  }

  /**
   * Returns published products with Commerce-resolved prices and availability.
   */
  public function catalog(string $query = '', string $category = 'all', float $budget = 0): array {
    if (strlen($query) > 100 || !in_array($category, [
      'all',
      'grinders',
      'brewing',
      'accessories',
    ], TRUE) || !is_finite($budget) || $budget < 0 || $budget > 1000) {
      throw new \InvalidArgumentException('Select an available category and a budget from 0 to 1000.');
    }
    $store = $this->store();
    $context = new Context($this->account, $store);
    $products = [];
    $ids = $this->entities->getStorage('commerce_product')->getQuery()
      ->accessCheck(TRUE)->condition('type', self::BUNDLE)->condition('status', 1)
      ->condition('stores', $store->id())->sort('product_id')->range(0, 24)->execute();
    foreach ($this->entities->getStorage('commerce_product')->loadMultiple($ids) as $product) {
      if (!$product->access('view') || !$this->fieldsReadable($product, [
        'title',
        'variations',
        'field_mcp_description',
        'field_mcp_category',
        'field_mcp_specs',
        'field_mcp_art',
      ])) {
        continue;
      }
      $kind = $product->get('field_mcp_category')->value;
      if (($category !== 'all' && $kind !== $category) || ($query !== '' && stripos($product->label() . ' ' . $product->get('field_mcp_description')->value, $query) === FALSE)) {
        continue;
      }
      $variants = [];
      foreach ($product->getVariations() as $variation) {
        if (!$variation->isPublished() || !$variation->access('view')
          || !$this->fieldsReadable($variation, ['title', 'price', 'sku'])) {
          continue;
        }
        $price = $this->prices->resolve($variation, '1', $context);
        if (!$price || $price->getCurrencyCode() !== $store->getDefaultCurrencyCode() || ($budget && (float) $price->getNumber() > $budget)) {
          continue;
        }
        $item = $this->manager->createOrderItem($variation);
        $variants[] = [
          'id' => (int) $variation->id(),
          'label' => $variation->label(),
          'price' => $this->money($price),
          'number' => $price->getNumber(),
          'available' => !$this->availability->check($item, $context)->isUnavailable(),
        ];
      }
      if (!$variants) {
        continue;
      }
      $art = $product->get('field_mcp_art')->value;
      if (!in_array($art, [
        'grinder',
        'electric',
        'kettle',
        'dripper',
        'scale',
        'carafe',
        'cup',
      ], TRUE)) {
        $art = 'cup';
      }
      $products[] = [
        'id' => (int) $product->id(),
        'title' => $product->label(),
        'description' => $product->get('field_mcp_description')->value,
        'category' => $kind,
        'variants' => $variants,
        'specs' => json_decode($product->get('field_mcp_specs')->value, TRUE, flags: JSON_THROW_ON_ERROR),
        'image' => '/' . $this->modules->getPath('mcp_apps_commerce_demo') . '/assets/' . $art . '.svg',
        'url' => $product->toUrl('canonical', ['absolute' => TRUE])->toString(),
      ];
    }
    return [
      'products' => $products,
      'currency' => $store->getDefaultCurrencyCode(),
      'query' => $query,
      'category' => $category,
      'budget' => $budget,
    ];
  }

  /**
   * Gets the customer's existing cart; viewing never creates an empty order.
   */
  public function cart(): array {
    $cart = $this->carts->getCart(self::BUNDLE, $this->store(), $this->account);
    return $cart ? $this->summary($this->owned($cart->id())) : [
      'id' => 0,
      'items' => [],
      'count' => 0,
      'total' => $this->money(NULL),
      'revision' => '',
      'state' => 'draft',
    ];
  }

  /**
   * Sets an absolute quantity using Commerce's cart manager.
   */
  public function setQuantity(int $variationId, int $quantity, string $revision): array {
    if ($quantity < 0 || $quantity > 10) {
      throw new \InvalidArgumentException('Choose a whole quantity from 0 to 10.');
    }
    return $this->synchronized(function () use ($variationId, $quantity, $revision) {
      $variation = $this->variation($variationId);
      $cart = $this->carts->getCart(self::BUNDLE, $this->store(), $this->account);
      if (!$cart && !$quantity) {
        return $this->cart();
      }
      $cart = $cart ? $this->owned($cart->id(), 'update') : $this->carts->createCart(self::BUNDLE, $this->store(), $this->account);
      if (!$cart->get('order_items')->access('edit')) {
        throw new \InvalidArgumentException('You cannot edit this basket.');
      }
      if ($revision !== '' && $revision !== $this->summary($cart)['revision']) {
        throw new \InvalidArgumentException('Your basket changed. Refresh it before changing quantities.');
      }
      $matched = NULL;
      foreach ($cart->getItems() as $item) {
        if ((int) $item->getPurchasedEntityId() === $variationId) {
          $matched = $item;
          break;
        }
      }
      if ($quantity) {
        $candidate = $this->manager->createOrderItem($variation, (string) $quantity);
        if (!$candidate->get('quantity')->access('edit')) {
          throw new \InvalidArgumentException('You cannot edit item quantities.');
        }
        if ($this->availability->check($candidate, new Context($this->account, $this->store()))->isUnavailable()) {
          throw new \InvalidArgumentException('That variation is currently unavailable.');
        }
        if ($matched) {
          if (!$matched->access('update') || !$matched->get('quantity')->access('edit')) {
            throw new \InvalidArgumentException('You cannot edit that basket item.');
          }
          $matched->setQuantity((string) $quantity);
          $this->manager->updateOrderItem($cart, $matched);
        }
        else {
          $this->manager->addEntity($cart, $variation, (string) $quantity);
        }
      }
      elseif ($matched) {
        if (!$matched->access('update') || !$matched->get('quantity')->access('edit')) {
          throw new \InvalidArgumentException('You cannot remove that basket item.');
        }
        $this->manager->removeOrderItem($cart, $matched);
      }
      foreach ($cart->getItems() as $item) {
        $item->get('purchased_entity')->entity = $this->variation((int) $item->getPurchasedEntityId());
      }
      $this->refresh->refresh($cart);
      $cart->save();
      return $this->summary($cart);
    });
  }

  /**
   * Places a demo order and records a simulated Commerce payment atomically.
   */
  public function checkout(int $orderId, string $revision): array {
    if (!$this->account->hasPermission('place mcp apps demo orders')) {
      throw new \InvalidArgumentException('You do not have permission to place test orders.');
    }
    return $this->synchronized(function () use ($orderId, $revision) {
      $cart = $this->owned($orderId);
      $gateway = $this->entities->getStorage('commerce_payment_gateway')->load(self::BUNDLE);
      if (!$gateway || !$gateway->status() || $gateway->getPluginId() !== 'mcp_apps_demo' || $gateway->getPlugin()->getMode() !== 'test') {
        throw new \InvalidArgumentException('Only the configured simulated payment gateway is allowed.');
      }
      if ($cart->getState()->getId() === 'completed' && !$cart->get('cart')->value) {
        if (!$cart->getData('mcp_apps_demo') || $cart->get('payment_gateway')->target_id !== $gateway->id()) {
          throw new \InvalidArgumentException('That order was not completed through the demo checkout.');
        }
        return $this->receipt($cart);
      }
      if (!$cart->access('update') || $cart->isLocked() || $cart->getState()->getId() !== 'draft' || !$cart->get('cart')->value || !$cart->hasItems()) {
        throw new \InvalidArgumentException('Select your unlocked, non-empty demo basket.');
      }
      foreach ($cart->getItems() as $item) {
        $variation = $this->variation((int) $item->getPurchasedEntityId());
        $item->get('purchased_entity')->entity = $variation;
        if (!$item->access('update') || !$this->fieldsReadable($item, [
          'quantity',
          'unit_price',
          'purchased_entity',
        ]) || $this->availability->check($item, new Context($this->account, $this->store()))->isUnavailable()) {
          throw new \InvalidArgumentException('A basket item is no longer available.');
        }
      }
      $this->refresh->refresh($cart);
      $cart->recalculateTotalPrice();
      if ($revision === '' || $revision !== $this->summary($cart)['revision']) {
        throw new \InvalidArgumentException('Prices or quantities changed. Refresh and review your basket again.');
      }
      if (!$cart->getTotalPrice() || !$cart->getTotalPrice()->isPositive()) {
        throw new \InvalidArgumentException('This basket has no payable total.');
      }
      $cart->set('payment_gateway', $gateway);
      $cart->setData('mcp_apps_demo', TRUE);
      $payment = $this->entities->getStorage('commerce_payment')->create([
        'type' => 'payment_manual',
        'payment_gateway' => $gateway->id(),
        'order_id' => $cart->id(),
        'amount' => $cart->getTotalPrice(),
      ]);
      $gateway->getPlugin()->createPayment($payment, TRUE);
      $cart->getState()->applyTransitionById('place');
      $cart->set('checkout_step', 'complete');
      $this->carts->finalizeCart($cart, FALSE);
      $cart->save();
      $this->carts->clearCaches();
      return $this->receipt($cart);
    });
  }

  /**
   * Enforces ownership even for users with administrative Commerce permissions.
   */
  private function owned(int|string $id, string $operation = 'view'): OrderInterface {
    $cart = $this->entities->getStorage('commerce_order')->loadUnchanged($id);
    if (!$cart instanceof OrderInterface || (int) $cart->getCustomerId() !== (int) $this->account->id() || $cart->bundle() !== self::BUNDLE || $cart->getStoreId() !== $this->store()->id() || !$cart->access($operation)) {
      throw new \InvalidArgumentException('That demo basket is not accessible.');
    }
    return $cart;
  }

  /**
   * Resolves a published variation within the dedicated demo catalog.
   */
  private function variation(int $id): ProductVariationInterface {
    $variation = $this->entities->getStorage('commerce_product_variation')->loadUnchanged($id);
    $product = $variation instanceof ProductVariationInterface ? $this->entities->getStorage('commerce_product')->loadUnchanged($variation->getProductId()) : NULL;
    if (!$product || !$variation->isPublished() || !$variation->access('view')
      || !$product->isPublished() || !$product->access('view')
      || $product->bundle() !== self::BUNDLE
      || !in_array($this->store()->id(), $product->getStoreIds(), TRUE)
      || !$this->fieldsReadable($variation, ['title', 'price'])
      || !$this->fieldsReadable($product, ['title', 'variations'])) {
      throw new \InvalidArgumentException('Select a published, accessible demo variation.');
    }
    return $variation;
  }

  /**
   * Checks each field before exposing it.
   */
  private function fieldsReadable(object $entity, array $fields): bool {
    foreach ($fields as $name) {
      if (!$entity->get($name)->access('view')) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Formats prices with Commerce currency rules.
   */
  private function money($price): string {
    return $this->formatter->format($price?->getNumber() ?? '0', $price?->getCurrencyCode() ?? $this->store()->getDefaultCurrencyCode());
  }

  /**
   * Returns a basket summary and server-issued revision.
   */
  private function summary(OrderInterface $cart): array {
    if (!$this->fieldsReadable($cart, ['order_items', 'total_price', 'state'])) {
      throw new \InvalidArgumentException('That basket summary is not accessible.');
    }
    $items = [];
    foreach ($cart->getItems() as $item) {
      if (!$item->access('view') || !$this->fieldsReadable($item, [
        'title',
        'quantity',
        'unit_price',
        'purchased_entity',
      ])) {
        throw new \InvalidArgumentException('A basket item is not accessible.');
      }
      $this->variation((int) $item->getPurchasedEntityId());
      $items[] = [
        'id' => (int) $item->id(),
        'variation_id' => (int) $item->getPurchasedEntityId(),
        'title' => $item->getPurchasedEntity()->getProduct()->label() . ' · ' . $item->getTitle(),
        'quantity' => (int) $item->getQuantity(),
        'unit_price' => $this->money($item->getUnitPrice()),
        'total' => $this->money($item->getTotalPrice()),
      ];
    }
    $number = $cart->getTotalPrice()?->getNumber() ?? '0';
    return [
      'id' => (int) $cart->id(),
      'items' => $items,
      'count' => array_sum(array_column($items, 'quantity')),
      'total' => $this->money($cart->getTotalPrice()),
      'revision' => hash('sha256', json_encode([
        (int) $cart->id(),
        $items,
        Calculator::trim($number),
        $cart->getAdjustments(),
      ], JSON_THROW_ON_ERROR)),
      'state' => $cart->getState()->getId(),
    ];
  }

  /**
   * Returns the test order confirmation.
   */
  private function receipt(OrderInterface $order): array {
    if (!$this->fieldsReadable($order, ['order_number', 'total_price', 'state'])) {
      throw new \InvalidArgumentException('That order confirmation is not accessible.');
    }
    return [
      'order_id' => (int) $order->id(),
      'number' => $order->getOrderNumber(),
      'total' => $this->money($order->getTotalPrice()),
      'state' => $order->getState()->getId(),
      'payment' => 'simulated',
      'url' => $order->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
  }

  /**
   * Serializes customer cart writes inside a database transaction.
   */
  private function synchronized(callable $operation): array {
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission('use mcp apps commerce demo')) {
      throw new \InvalidArgumentException('An authenticated demo shopper is required.');
    }
    $key = 'mcp_apps_commerce:' . $this->account->id() . ':' . $this->store()->id();
    if (!$this->lock->acquire($key, 60)) {
      throw new \InvalidArgumentException('Another basket operation is in progress. Try again.');
    }
    $transaction = $this->database->startTransaction();
    try {
      return $operation();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      $this->entities->getStorage('commerce_order')->resetCache();
      $this->entities->getStorage('commerce_order_item')->resetCache();
      $this->carts->clearCaches();
      throw $e;
    }
    finally {
      unset($transaction);
      $this->lock->release($key);
    }
  }

}
