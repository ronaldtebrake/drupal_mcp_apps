<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\commerce_price\CurrencyImporter;

/**
 * Explicit, repeatable setup; preserves existing products and other stores.
 */
final class DemoSeed {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly CurrencyImporter $currencies,
    private readonly ConfigFactoryInterface $config,
  ) {}

  /**
   * Creates the explicit catalog without overwriting existing products.
   */
  public function seed(): array {
    $this->currencies->import('EUR');
    $configs = [
      'commerce_order_item_type' => [
        'id' => Store::BUNDLE,
        'label' => 'MCP Apps demo item',
        'purchasableEntityType' => 'commerce_product_variation',
        'orderType' => Store::BUNDLE,
      ],
      'commerce_order_type' => [
        'id' => Store::BUNDLE,
        'label' => 'MCP Apps test order',
        'workflow' => 'order_default',
        'numberPattern' => 'order_default',
        'refresh_mode' => 'customer',
        'refresh_frequency' => 300,
        'sendReceipt' => FALSE,
      ],
      'commerce_product_variation_type' => [
        'id' => Store::BUNDLE,
        'label' => 'MCP Apps demo variation',
        'orderItemType' => Store::BUNDLE,
        'generateTitle' => FALSE,
      ],
      'commerce_product_type' => [
        'id' => Store::BUNDLE,
        'label' => 'MCP Apps demo product',
        'variationTypes' => [Store::BUNDLE],
        'multipleVariations' => TRUE,
        'injectVariationFields' => TRUE,
      ],
      'commerce_payment_gateway' => [
        'id' => Store::BUNDLE,
        'label' => 'MCP Apps simulated payment',
        'plugin' => 'mcp_apps_demo',
        'configuration' => [
          'mode' => 'test',
          'display_label' => 'Test payment · no real charge',
        ],
      ],
    ];
    foreach ($configs as $type => $values) {
      $storage = $this->entities->getStorage($type);
      if (!$storage->load(Store::BUNDLE)) {
        $storage->create($values)->save();
      }
    }
    foreach ([
      'description',
      'category',
      'specs',
      'art',
    ] as $name) {
      $field = 'field_mcp_' . $name;
      if (!FieldStorageConfig::loadByName('commerce_product', $field)) {
        FieldStorageConfig::create([
          'entity_type' => 'commerce_product',
          'field_name' => $field,
          'type' => 'string_long',
        ])->save();
      }
      if (!FieldConfig::loadByName('commerce_product', Store::BUNDLE, $field)) {
        FieldConfig::create([
          'entity_type' => 'commerce_product',
          'bundle' => Store::BUNDLE,
          'field_name' => $field,
          'label' => ucfirst($name),
        ])->save();
      }
    }
    $storeStorage = $this->entities->getStorage('commerce_store');
    $uuid = $this->config->get('mcp_apps_commerce_demo.settings')->get('store_uuid');
    $stores = $storeStorage->loadByProperties(['uuid' => $uuid]);
    $store = reset($stores);
    if (!$store) {
      $store = $storeStorage->create([
        'type' => 'online',
        'uuid' => $uuid,
        'uid' => 1,
        'name' => 'Fieldwork Supply · demo',
        'mail' => 'demo@example.com',
        'default_currency' => 'EUR',
        'timezone' => 'Europe/Amsterdam',
        'address' => [
          'country_code' => 'NL',
          'address_line1' => 'Demo street 1',
          'locality' => 'Utrecht',
          'postal_code' => '3511 AA',
        ],
        'billing_countries' => ['NL'],
        'is_default' => FALSE,
      ]);
      $store->save();
    }
    $products = [
      [
        'studio-grinder',
        'Studio hand grinder',
        'grinders',
        'grinder',
        'A precise daily ritual, without the noise. Stainless steel burrs in a compact aluminium body.',
        [
          [
            'label' => 'Burrs',
            'value' => '38 mm steel',
          ],
          [
            'label' => 'Best for',
            'value' => 'Filter & espresso',
          ],
          ['label' => 'Weight', 'value' => '460 g'],
        ],
        [
          'Graphite' => '89.00',
          'Sand' => '89.00',
        ],
      ],
      [
        'atelier-grinder',
        'Atelier electric grinder',
        'grinders',
        'electric',
        'One touch, freshly ground. A quiet single-dose grinder for your kitchen counter.',
        [
          [
            'label' => 'Burrs',
            'value' => '48 mm steel',
          ],
          [
            'label' => 'Best for',
            'value' => 'Filter coffee',
          ],
          [
            'label' => 'Weight',
            'value' => '2.1 kg',
          ],
        ],
        [
          'Graphite' => '149.00',
          'Chalk' => '159.00',
        ],
      ],
      [
        'precision-kettle',
        'Precision pour kettle',
        'brewing',
        'kettle',
        'A balanced pour and a comfortable grip. Make every cup a little more intentional.',
        [
          [
            'label' => 'Capacity',
            'value' => '900 ml',
          ],
          [
            'label' => 'Material',
            'value' => 'Stainless steel',
          ],
          [
            'label' => 'Best for',
            'value' => 'Pour-over',
          ],
        ],
        [
          'Graphite' => '69.00',
          'Chalk' => '74.00',
        ],
      ],
      [
        'ceramic-dripper',
        'Ceramic pour-over',
        'brewing',
        'dripper',
        'Simple, beautiful and easy to get right. A ceramic dripper for one or two cups.',
      [
      [
        'label' => 'Capacity',
        'value' => '1–2 cups',
      ],
      [
        'label' => 'Material',
        'value' => 'Ceramic',
      ],
      [
        'label' => 'Best for',
        'value' => 'Filter coffee',
      ],
      ],
        ['Chalk' => '28.00', 'Clay' => '28.00'],
      ],
      [
        'brew-scale',
        'Everyday brew scale',
        'accessories',
        'scale',
        'A small footprint. A clear display. Everything you need to dial in your next brew.',
        [
          [
            'label' => 'Precision',
            'value' => '0.1 g',
          ],
          [
            'label' => 'Battery',
            'value' => 'USB-C',
          ],
          [
            'label' => 'Timer',
            'value' => 'Built in',
          ],
        ],
        ['Graphite' => '39.00'],
      ],
      [
        'glass-carafe',
        'Morning glass carafe',
        'accessories',
        'carafe',
        'Pour, share and take your time. Heat-resistant glass with a natural cork collar.',
        [
          [
            'label' => 'Capacity',
            'value' => '600 ml',
          ],
          [
            'label' => 'Material',
            'value' => 'Borosilicate glass',
          ],
          [
            'label' => 'Best for',
            'value' => 'Sharing',
          ],
        ],
        ['Clear' => '32.00'],
      ],
    ];
    $ids = [];
    foreach ($products as [$sku, $title, $category, $art, $description, $specs, $options]) {
      $existing = $this->entities->getStorage('commerce_product_variation')->loadByProperties([
        'sku' => 'MCP-' . $sku . '-' . array_key_first($options),
      ]);
      if ($existing) {
        $ids[] = (int) reset($existing)->getProductId();
        continue;
      }
      $variants = [];
      foreach ($options as $option => $price) {
        $variant = $this->entities->getStorage('commerce_product_variation')->create([
          'type' => Store::BUNDLE,
          'sku' => 'MCP-' . $sku . '-' . $option,
          'title' => $option,
          'status' => TRUE,
          'price' => [
            'number' => $price,
            'currency_code' => 'EUR',
          ],
        ]);
        $variant->save();
        $variants[] = $variant;
      }
      $product = $this->entities->getStorage('commerce_product')->create([
        'type' => Store::BUNDLE,
        'title' => $title,
        'status' => TRUE,
        'stores' => [$store],
        'variations' => $variants,
        'field_mcp_description' => $description,
        'field_mcp_category' => $category,
        'field_mcp_specs' => json_encode($specs, JSON_THROW_ON_ERROR),
        'field_mcp_art' => $art,
      ]);
      $product->save();
      $ids[] = (int) $product->id();
    }
    return [
      'store_id' => (int) $store->id(),
      'product_ids' => $ids,
    ];
  }

}
