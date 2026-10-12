<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Checks the native website transport and its permission and CSRF boundaries.
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class CommerceAccessTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mcp_apps_commerce_demo'];
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Checks website access, input validation and CSRF protection.
   */
  public function testWebsiteAndTransport(): void {
    $this->container->get('mcp_apps_commerce_demo.seed')->seed();
    $this->drupalGet('/shop');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet('/shop');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogout();
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'use mcp apps composer',
      'use mcp apps commerce demo',
    ]));
    $this->drupalGet('/shop', ['query' => ['category' => 'grinders', 'budget' => 150]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.fieldwork-storefront');
    $this->assertSession()->elementsCount('css', 'h1', 1);
    $this->assertSession()->responseContains('Atelier electric grinder');
    $this->assertSession()->responseNotContains('Precision pour kettle');
    $this->assertSession()->responseNotContains('data-shop-review');
    preg_match('/data-drupal-selector="drupal-settings-json">(.+?)<\/script>/s', $this->getSession()->getPage()->getContent(), $match);
    $settings = json_decode($match[1], TRUE, flags: JSON_THROW_ON_ERROR)['mcpAppsCommerce'];
    $client = $this->getSession()->getDriver()->getClient();
    $body = json_encode([
      'name' => 'commerce_shop_filter',
      'arguments' => [
        'session_id' => $settings['boot']['session_id'],
        'category' => 'brewing',
      ],
    ], JSON_THROW_ON_ERROR);
    $client->request('POST', $this->buildUrl('/shop/action'), [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    $this->assertSession()->statusCodeEquals(403);
    $headers = [
      'CONTENT_TYPE' => 'application/json',
      'HTTP_X_CSRF_TOKEN' => $settings['token'],
    ];
    $client->request('POST', $this->buildUrl('/shop/action'), [], [], $headers, $body);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Precision pour kettle');
    $client->request('POST', $this->buildUrl('/shop/action'), [], [], $headers, json_encode([
      'name' => 'system_clear_cache',
      'arguments' => [],
    ]));
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet('/shop/app');
    $this->assertSession()->statusCodeEquals(404);
    $this->drupalGet('/shop', ['query' => ['category' => 'unknown']]);
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet('/shop', ['query' => ['budget' => 'invalid']]);
    $this->assertSession()->statusCodeEquals(400);
    $this->container->get('module_installer')->install(['mcp_apps_commerce_dev']);
    $this->drupalGet('/shop/app');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('__DRUPAL_BROWSER_PREVIEW__');
  }

}
