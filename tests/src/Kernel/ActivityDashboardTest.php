<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp_apps_activity_demo\Dashboard;
use Drupal\mcp_apps_activity_demo\DashboardComposition;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies reusable data, native chart assets and dashboard access boundaries.
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class ActivityDashboardTest extends KernelTestBase {
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'serialization', 'tool', 'mcp_server',
    'mcp_server_tool_bridge', 'mcp_apps', 'mcp_apps_openui',
    'mcp_apps_activity_demo',
  ];

  /**
   * Tests the complete dashboard without Canvas, Media or a demo theme.
   */
  public function testNativeDashboardAndAccess(): void {
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'mcp_server', 'mcp_apps_openui', 'mcp_apps_activity_demo']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'stark')->save();
    $allowed = $this->createUser(['use mcp apps composer']);
    $dashboard = $this->container->get('mcp_apps_activity_demo.dashboard');
    $provider = $this->container->get('plugin.manager.mcp_server.resource_provider')->createInstance('mcp_apps_activity');
    foreach ([new AnonymousUserSession(), $this->createUser(), $allowed] as $account) {
      $this->setCurrentUser($account);
      $access = $account->id() === $allowed->id();
      $this->assertSame($access, $provider->checkAccess(Dashboard::URI, $account)->isAllowed());
      foreach (['editorial_activity', 'activity_dashboard_open'] as $id) {
        $this->assertSame($access, $this->container->get('plugin.manager.tool')->createInstance($id)->access());
      }
    }
    $this->assertFalse($this->container->get('module_handler')->moduleExists('canvas'));
    $this->assertFalse($this->container->get('module_handler')->moduleExists('mcp_apps_activity_dev'));
    $data = $dashboard->invoke('editorial_activity', [])['structuredContent']['data'];
    $this->assertTrue($data['demo']);
    $this->assertCount(30, $data['series']);
    $this->assertSame(array_sum(array_column($data['articles'], 'reads')), $data['reads']);
    $this->assertSame(array_sum($data['series']), $data['reads']);
    foreach ([1, 13, 14, 30] as $days) {
      $custom = $dashboard->invoke('editorial_activity', ['days' => $days])['structuredContent']['data'];
      $this->assertCount($days, $custom['series']);
      $this->assertCount($days, $custom['previous']);
      $this->assertSame(array_slice($data['series'], -$days), $custom['series']);
      $this->assertSame(array_sum($custom['series']), $custom['reads']);
      if ($days <= 15) {
        $this->assertSame(array_slice($data['series'], -2 * $days, $days), $custom['previous']);
      }
    }
    $this->assertArrayNotHasKey('mcp', $this->container->get('plugin.manager.tool')->createInstance('editorial_activity')->execute()->getResult()->getMeta());
    $opened = $dashboard->open(30, 'all');
    $session = $opened['data']['session_id'];
    $this->assertSame(DashboardComposition::tree($data), $opened['ui']['tree']);
    $preview = $this->container->get('mcp_apps_openui.composer')->preview($session, $opened['ui']['tree']);
    $this->assertStringContainsString('Readership over time', $preview['ui']['html']);
    $this->assertStringContainsString('A weekend of discovery in Rotterdam', $preview['ui']['html']);
    $assets = $this->container->get('mcp_apps_openui.sessions')->get($session)['assets'];
    $this->assertNotEmpty(array_filter($assets, static fn($asset) => $asset['mime'] === 'text/javascript' && str_contains($asset['bytes'], 'Chart.js')));
    $this->container->get('theme_installer')->install(['mcp_apps_activity_test_subtheme']);
    $dashboard = $this->container->get('mcp_apps_activity_demo.dashboard');
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'mcp_apps_activity_test_subtheme')->save();
    $switched = $dashboard->filter($session, 30, 'all');
    $this->assertSame('mcp_apps_activity_test_subtheme', $switched['ui']['theme']);
    $this->assertSame($opened['ui']['tree'], $switched['ui']['tree']);
    $themeManager = $this->container->get('theme.manager');
    $previousTheme = $themeManager->getActiveTheme();
    $this->container->get('mcp_apps_openui.composer')->preview($session, $switched['ui']['tree']);
    $this->assertSame($previousTheme, $themeManager->getActiveTheme());
    $themedAssets = $this->container->get('mcp_apps_openui.sessions')->get($session)['assets'];
    $this->assertNotEmpty(array_filter($themedAssets, static fn($asset) => str_contains($asset['bytes'], '--primary: #123abc')));
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'stark')->save();
    $filtered = $dashboard->filter($session, 7, 'culture');
    $this->assertLessThan($data['reads'], $filtered['data']['reads']);
    $report = $dashboard->invoke('editorial_activity', ['days' => 7, 'section' => 'culture'])['structuredContent']['data'];
    $this->assertSame(['culture'], array_values(array_unique(array_column($report['articles'], 'section'))));
    foreach (['readership' => 'chart', 'stories' => 'content', 'activity' => 'activity', 'metrics' => 'metrics'] as $view => $slot) {
      $focused = $dashboard->open(30, 'all', $view);
      $this->assertSame($opened['ui']['tree'][0]['slots'][$slot], $focused['ui']['tree']);
      $updated = $dashboard->filter($focused['data']['session_id'], 7, 'culture');
      $this->assertSame($view, $updated['ui']['view']);
      $this->assertSame(DashboardComposition::tree($report, $view), $updated['ui']['tree']);
      $this->assertStringContainsString('view=' . $view, $updated['ui']['website_url']);
      $html = $this->container->get('mcp_apps_openui.composer')->preview($focused['data']['session_id'], $updated['ui']['tree']);
      $this->assertStringNotContainsString('class="pulse-dashboard"', $html['ui']['html']);
    }
    $invalidView = $this->container->get('plugin.manager.tool')->createInstance('activity_dashboard_open');
    $invalidView->setInputValue('view', 'unknown');
    $this->assertFalse($invalidView->access());
    foreach ([0, -1, 31, 365] as $days) {
      foreach (['editorial_activity', 'activity_dashboard_open', 'activity_dashboard_filter'] as $name) {
        $invalid = $this->container->get('plugin.manager.tool')->createInstance($name);
        $invalid->setInputValue('days', $days);
        if ($name === 'activity_dashboard_filter') {
          $invalid->setInputValue('session_id', $session);
        }
        $this->assertFalse($invalid->access());
        $this->assertGreaterThan(0, $invalid->validateInputs()->count());
      }
    }
    $customView = $dashboard->open(14, 'all', 'readership');
    $this->assertCount(14, $customView['ui']['tree'][0]['props']['current']);
    $customView = $dashboard->filter($customView['data']['session_id'], 13, 'culture');
    $this->assertSame('readership', $customView['ui']['view']);
    $this->assertCount(13, $customView['ui']['tree'][0]['props']['current']);
    $this->setCurrentUser($this->createUser(['use mcp apps composer']));
    $this->expectException(\InvalidArgumentException::class);
    $dashboard->filter($session, 7, 'culture');
  }

}
