<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the website, browser app and its CSRF-protected tool transport.
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class ActivityDevelopmentTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mcp_apps_activity_dev'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests access, native rendering and the transport allowlist.
   */
  public function testAccessAndTransport(): void {
    $base = '/admin/content/mcp-apps/activity';
    foreach ([$base, $base . '/app'] as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet($base);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogout();
    $this->drupalLogin($this->drupalCreateUser(['use mcp apps composer']));
    $this->drupalGet($base, ['query' => ['days' => 7, 'section' => 'culture']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('data-pulse-chart');
    $this->assertSession()->responseContains('The city after dark');
    $this->assertSession()->responseNotContains('Five places for your first coffee');
    $this->drupalGet($base, ['query' => ['days' => 7, 'section' => 'culture', 'view' => 'stories']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Stories making an impact');
    $this->assertSession()->responseContains('view=stories');
    $this->assertSession()->responseNotContains('data-pulse-chart');
    $this->drupalGet($base, ['query' => ['days' => 365]]);
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet($base, ['query' => ['view' => 'unknown']]);
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet($base . '/app', ['query' => ['view' => 'stories']]);
    $this->assertSession()->statusCodeEquals(200);
    preg_match('/window\.__DRUPAL_BROWSER_PREVIEW__=(.+?);<\/script>/', $this->getSession()->getPage()->getContent(), $matches);
    $boot = json_decode($matches[1], TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('stories', $boot['result']['structuredContent']['data']['view']);
    $client = $this->getSession()->getDriver()->getClient();
    $headers = ['CONTENT_TYPE' => 'application/json'];
    $body = json_encode([
      'name' => 'activity_dashboard_filter',
      'arguments' => [
        'session_id' => $boot['result']['structuredContent']['data']['session_id'],
        'days' => 7,
        'section' => 'culture',
      ],
    ]);
    $client->request('POST', $this->buildUrl($base . '/call'), [], [], $headers, $body);
    $this->assertSession()->statusCodeEquals(403);
    $headers['HTTP_X_CSRF_TOKEN'] = $boot['token'];
    $client->request('POST', $this->buildUrl($base . '/call'), [], [], $headers, $body);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('culture');
    $this->assertSession()->responseContains('"view":"stories"');
    $body = json_encode(['name' => 'activity_dashboard_filter', 'arguments' => ['unexpected' => 'value']]);
    $client->request('POST', $this->buildUrl($base . '/call'), [], [], $headers, $body);
    $this->assertSession()->statusCodeEquals(400);
    $body = json_encode(['name' => 'entity_save', 'arguments' => []]);
    $client->request('POST', $this->buildUrl($base . '/call'), [], [], $headers, $body);
    $this->assertSession()->statusCodeEquals(400);
  }

}
