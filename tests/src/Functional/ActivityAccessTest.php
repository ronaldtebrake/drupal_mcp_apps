<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the website works without development routes.
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class ActivityAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mcp_apps_activity_demo'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests website access and the absence of development routes.
   */
  public function testAccessAndTransport(): void {
    $base = '/admin/content/mcp-apps/activity';
    $this->drupalGet($base);
    $this->assertSession()->statusCodeEquals(403);
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
    $this->submitForm(['days' => 13, 'section' => 'culture'], 'Apply filters');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('days', '13');
    $this->assertSession()->responseContains('Stories making an impact');
    $this->assertSession()->responseNotContains('data-pulse-chart');
    $this->drupalGet($base, ['query' => ['days' => 365]]);
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet($base, ['query' => ['days' => '14.5']]);
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet($base, ['query' => ['view' => 'unknown']]);
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet($base . '/app');
    $this->assertSession()->statusCodeEquals(404);
    $this->getSession()->getDriver()->getClient()->request('POST', $this->buildUrl($base . '/call'));
    $this->assertSession()->statusCodeEquals(404);
  }

}
