<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\commerce\Context;
use Drupal\commerce_order\AvailabilityCheckerInterface;
use Drupal\commerce_order\AvailabilityResult;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\mcp_apps_commerce_demo\Shop;
use Drupal\mcp_apps_commerce_demo\Store;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies actual Commerce persistence, access checks and payment boundaries.
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class CommerceShopTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_cart', 'commerce_payment', 'commerce_checkout', 'token',
    'serialization', 'tool', 'mcp_server', 'mcp_server_tool_bridge',
    'mcp_apps', 'mcp_apps_openui', 'mcp_apps_commerce_demo',
    'mcp_apps_commerce_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    $this->installConfig([
      'commerce_payment',
      'commerce_checkout',
      'mcp_server',
      'mcp_apps_openui',
      'mcp_apps_commerce_demo',
    ]);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->container->get('mcp_apps_commerce_demo.seed')->seed();
  }

  /**
   * Checks shared native rendering and account isolation.
   */
  public function testSharedComponentsAndPermissions(): void {
    $shop = $this->container->get('mcp_apps_commerce_demo.shop');
    $provider = $this->container->get('plugin.manager.mcp_server.resource_provider')->createInstance('mcp_apps_commerce');
    $allowed = $this->createUser([
      'view commerce_product',
      'access content',
      'use mcp apps composer',
      'use mcp apps commerce demo',
    ]);
    foreach ([
      new AnonymousUserSession(),
      $this->createUser(['access content']),
      $allowed,
    ] as $account) {
      $this->setCurrentUser($account);
      $expected = $account->id() === $allowed->id();
      $this->assertSame($expected, $provider->checkAccess(Shop::URI, $account)->isAllowed());
      $this->assertSame($expected, $this->container->get('plugin.manager.tool')->createInstance('commerce_shop_open')->access());
      $this->assertFalse($this->container->get('plugin.manager.tool')->createInstance('commerce_demo_checkout')->access());
    }
    $this->assertFalse($this->container->get('module_handler')->moduleExists('canvas'));
    $this->assertFalse($this->container->get('module_handler')->moduleExists('mcp_apps_commerce_dev'));
    $seed = $this->container->get('mcp_apps_commerce_demo.seed')->seed();
    $this->assertCount(6, $seed['product_ids']);
    $this->assertSame($this->store->id(), $this->container->get('entity_type.manager')->getStorage('commerce_store')->loadDefault()->id());
    $choosing = $shop->open('', 'grinders', 150);
    $choosingPreview = $this->container->get('mcp_apps_openui.composer')->preview($choosing['data']['session_id'], $choosing['ui']['tree']);
    $this->assertSame(2, substr_count($choosingPreview['ui']['html'], 'class="shop-specs" open'));
    $this->assertStringContainsString('Filter &amp; espresso', $choosingPreview['ui']['html']);
    $this->assertStringNotContainsString('data-shop-compare', $choosingPreview['ui']['html']);
    $this->assertSame([], $choosing['ui']['tree'][0]['slots']['comparison']);
    $opened = $shop->open('', 'grinders', 150, 'browse');
    $this->assertSame(2, $opened['data']['products']);
    $this->assertSame(0, $opened['ui']['cart']['id']);
    $this->assertSame(0, $this->countEntities('commerce_order'));
    $tree = $opened['ui']['tree'];
    $preview = $this->container->get('mcp_apps_openui.composer')->preview($opened['data']['session_id'], $tree);
    $this->assertStringContainsString('Atelier electric grinder', $preview['ui']['html']);
    $this->assertStringContainsString('data-shop-form="cart"', $preview['ui']['html']);
    $this->assertStringNotContainsString('data-shop-review', $preview['ui']['html']);
    $this->assertStringNotContainsString('shop-basket-card', $preview['ui']['html']);
    $this->assertStringNotContainsString('GOOD THINGS, EVERY DAY.', $preview['ui']['html']);
    $this->assertStringNotContainsString('DEMO STORE', $preview['ui']['html']);
    $assets = $this->container->get('mcp_apps_openui.sessions')->get($opened['data']['session_id'])['assets'];
    $this->assertNotEmpty(array_filter($assets, static fn($asset) => $asset['mime'] === 'image/svg+xml'));
    $this->assertArrayNotHasKey('html', $preview['data']);
    $comparison = $shop->filter($opened['data']['session_id'], '', 'grinders', 150, [
      $seed['product_ids'][0],
      $seed['product_ids'][1],
    ]);
    $this->assertSame('mcp_apps_commerce_demo:comparison', $comparison['ui']['tree'][0]['slots']['comparison'][0]['component']);
    $session = $opened['data']['session_id'];
    $variant = $this->container->get('mcp_apps_commerce_demo.store')->catalog()['products'][0]['variants'][0]['id'];
    $added = $shop->cart($session, $variant, 1, '');
    $this->assertSame('catalog', $added['ui']['view']);
    $this->assertSame([], $added['ui']['tree'][0]['slots']['basket']);
    $this->assertSame('mcp_apps_commerce_demo:basket_summary', $added['ui']['tree'][0]['slots']['summary'][0]['component']);
    $basket = $shop->filter($session, '', 'grinders', 150, $added['ui']['compare'], 'basket');
    $this->assertSame([], $basket['ui']['tree'][0]['slots']['products']);
    $basketPreview = $this->container->get('mcp_apps_openui.composer')->preview($session, $basket['ui']['tree']);
    $this->assertStringContainsString('Your basket', $basketPreview['ui']['html']);
    $this->assertStringNotContainsString('data-shop-form="filter"', $basketPreview['ui']['html']);
    $returned = $shop->filter($session, '', 'grinders', 150, $added['ui']['compare'], 'catalog');
    $this->assertSame($added['ui']['compare'], $returned['ui']['compare']);
    $this->assertSame($added['ui']['filters'], $returned['ui']['filters']);
    $legacy = $shop->invoke('commerce_shop_filter', [
      'session_id' => $session,
      'category' => 'grinders',
      'budget' => 150,
    ]);
    $this->assertSame('catalog', $legacy['_meta']['ui']['view']);
    $this->assertSame('browse', $legacy['_meta']['ui']['mode']);
    $this->assertDenied(fn() => $shop->filter($session, '', 'grinders', 150, [], 'invalid'));
    $this->assertDenied(fn() => $shop->open('', 'grinders', 150, 'invalid'));
    $this->setCurrentUser($this->createUser([
      'view commerce_product',
      'access content',
      'use mcp apps composer',
      'use mcp apps commerce demo',
      'deny demo price',
    ]));
    $this->assertSame([], $this->container->get('mcp_apps_commerce_demo.store')->catalog()['products']);
    $this->setCurrentUser($this->createUser([
      'view commerce_product',
      'access content',
      'use mcp apps composer',
      'use mcp apps commerce demo',
    ]));
    $this->expectException(\InvalidArgumentException::class);
    $shop->view($opened['data']['session_id']);
  }

  /**
   * Checks shopper carts, rollback and simulated checkout.
   */
  public function testCartCheckoutAndIsolation(): void {
    $account = $this->createUser([
      'view commerce_product',
      'access content',
      'use mcp apps composer',
      'use mcp apps commerce demo',
      'place mcp apps demo orders',
    ]);
    $this->setCurrentUser($account);
    $adapter = $this->container->get('mcp_apps_commerce_demo.store');
    $products = $adapter->catalog()['products'];
    $variation = $products[0]['variants'][0]['id'];
    $cart = $adapter->setQuantity($variation, 1, '');
    $this->assertSame(1, $cart['count']);
    $again = $adapter->setQuantity($variation, 1, '');
    $this->assertSame($cart['id'], $again['id']);
    $this->assertSame(1, $again['count']);
    $cart = $adapter->setQuantity($variation, 2, $cart['revision']);
    $this->assertSame(2, $cart['count']);
    $this->assertSame(1, $this->countEntities('commerce_order_item'));
    $empty = $adapter->setQuantity($variation, 0, $cart['revision']);
    $this->assertSame(0, $empty['count']);
    $cart = $adapter->setQuantity($variation, 2, $empty['revision']);

    $this->setCurrentUser($this->createUser([
      'view commerce_product',
      'access content',
      'use mcp apps commerce demo',
      'place mcp apps demo orders',
      'administer commerce_order',
    ]));
    $this->assertDenied(fn() => $adapter->checkout($cart['id'], $cart['revision']));
    $this->assertSame(0, $adapter->cart()['count']);
    $this->setCurrentUser($account);

    $gateway = $this->container->get('entity_type.manager')->getStorage('commerce_payment_gateway')->load(Store::BUNDLE);
    $gateway->setPluginId('manual');
    $gateway->save();
    $this->assertDenied(fn() => $adapter->checkout($cart['id'], $cart['revision']));
    $this->assertSame(0, $this->countEntities('commerce_payment'));
    $gateway->setPluginId('mcp_apps_demo');
    $gateway->setPluginConfiguration(['mode' => 'test']);
    $gateway->save();

    $entity = $this->container->get('entity_type.manager')->getStorage('commerce_product_variation')->load($variation);
    $entity->setPrice(new Price('99.00', 'EUR'));
    $entity->save();
    $this->assertDenied(fn() => $adapter->checkout($cart['id'], $cart['revision']));
    $this->assertSame(0, $this->countEntities('commerce_payment'));
    $cart = $adapter->setQuantity($variation, 2, '');
    $entity->setUnpublished();
    $entity->save();
    $this->assertFalse($this->container->get('entity_type.manager')->getStorage('commerce_product_variation')->loadUnchanged($variation)->isPublished());
    $this->assertSame('draft', $this->container->get('entity_type.manager')->getStorage('commerce_order')->loadUnchanged($cart['id'])->getState()->getId());
    $this->assertDenied(fn() => $adapter->checkout($cart['id'], $cart['revision']));
    $entity->setPublished();
    $entity->save();
    $dispatcher = $this->container->get('event_dispatcher');
    $failure = static function (): void {
      throw new \RuntimeException('Fixture: order placement failed after payment.');
    };
    $dispatcher->addListener('commerce_order.place.post_transition', $failure);
    try {
      $adapter->checkout($cart['id'], $cart['revision']);
      $this->fail('The order-placement failure was not propagated.');
    }
    catch (EntityStorageException $e) {
      $this->assertSame('Fixture: order placement failed after payment.', $e->getMessage());
    }
    finally {
      $dispatcher->removeListener('commerce_order.place.post_transition', $failure);
    }
    $this->assertSame(0, $this->countEntities('commerce_payment'));
    $this->assertSame('draft', $adapter->cart()['state']);
    $this->assertSame(2, $adapter->cart()['count']);
    $receipt = $adapter->checkout($cart['id'], $cart['revision']);
    $this->assertSame('completed', $receipt['state']);
    $this->assertSame('simulated', $receipt['payment']);
    $this->assertSame($receipt, $adapter->checkout($cart['id'], $cart['revision']));
    $this->assertSame(1, $this->countEntities('commerce_payment'));
    $order = $this->container->get('entity_type.manager')->getStorage('commerce_order')->loadUnchanged($cart['id']);
    $this->assertTrue($order->getTotalPrice()->equals(new Price('198.00', 'EUR')));
    $this->assertFalse((bool) $order->get('cart')->value);
    $payments = $this->container->get('entity_type.manager')->getStorage('commerce_payment')->loadMultiple();
    $payment = reset($payments);
    $this->assertSame('completed', $payment->getState()->getId());
    $this->assertSame('test', $payment->getPaymentGatewayMode());
    $this->assertSame(0, $adapter->cart()['id']);

    $this->setCurrentUser($this->createUser([
      'view commerce_product',
      'use mcp apps commerce demo',
      'deny demo quantity',
    ]));
    $this->assertDenied(fn() => $adapter->setQuantity($variation, 1, ''));
    $this->assertSame(0, $adapter->cart()['id']);

    $this->setCurrentUser($this->createUser([
      'view commerce_product',
      'access content',
      'use mcp apps commerce demo',
    ]));
    $orders = $this->countEntities('commerce_order');
    $this->container->get('commerce_order.availability_manager')->addChecker(new class implements AvailabilityCheckerInterface {

      /**
       * {@inheritdoc}
       */
      public function applies(OrderItemInterface $order_item) {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      public function check(OrderItemInterface $order_item, Context $context) {
        return AvailabilityResult::unavailable('Fixture is unavailable.');
      }

    });
    $this->assertDenied(fn() => $adapter->setQuantity($variation, 1, ''));
    $this->assertSame($orders, $this->countEntities('commerce_order'));
    $this->assertSame(0, $adapter->cart()['id']);
    $this->assertDenied(fn() => $adapter->checkout($cart['id'], $cart['revision']));
  }

  /**
   * Counts persisted entities for rollback assertions.
   */
  private function countEntities(string $type): int {
    return (int) $this->container->get('entity_type.manager')->getStorage($type)->getQuery()->accessCheck(FALSE)->count()->execute();
  }

  /**
   * Requires the operation to reject invalid or inaccessible input.
   */
  private function assertDenied(callable $operation): void {
    try {
      $operation();
      $this->fail('An inaccessible or invalid Commerce operation succeeded.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertNotEmpty($e->getMessage());
    }
  }

}
