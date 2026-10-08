<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal MCP Apps integration.
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class CompositionTest extends KernelTestBase {
  use UserCreationTrait;
  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'serialization',
    'tool',
    'mcp_server',
    'mcp_server_tool_bridge',
    'mcp_apps',
    'mcp_apps_openui',
    'mcp_apps_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'mcp_server', 'mcp_apps_openui']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->setCurrentUser($this->createUser(['use mcp apps composer']));
  }

  /**
   * Tests standalone nested rendering and assets.
   */
  public function testStandaloneNestedRenderingAndAssets(): void {
    $this->assertFalse($this->container->get('module_handler')->moduleExists('canvas'));
    $tree = [
      [
        'component' => 'mcp_apps_test:panel',
        'props' => ['tone' => 'quiet'],
        'slots' => [
          'content' => [
            ['component' => 'mcp_apps_test:text', 'props' => ['text' => 'Native nested Twig'], 'slots' => []],
          ],
        ],
      ],
    ];
    $composer = $this->container->get('mcp_apps_openui.composer');
    $opened = $composer->open('Standalone', 'stark', '');
    $id = $opened['data']['session_id'];
    $preview = $composer->preview($id, $tree);
    $this->assertStringContainsString('panel-quiet', $preview['ui']['html']);
    $this->assertStringContainsString('<p>Native nested Twig</p>', $preview['ui']['html']);
    $this->assertNotEmpty($preview['ui']['assets']);
    $asset = array_key_first($preview['ui']['assets']);
    $this->assertNotEmpty($composer->asset($id, $asset, 0)['ui']['base64']);
    $this->assertArrayNotHasKey('html', $preview['data']);
    $catalog = $this->container->get('mcp_apps_openui.catalog')->list('mcp_apps_test');
    $this->assertFalse($catalog['mcp_apps_test:object']['supported']);
    $invalid = [
      [['component' => 'mcp_apps_test:panel', 'props' => ['tone' => 'wrong']]],
      [['component' => 'mcp_apps_test:text', 'props' => []]],
      [['component' => 'mcp_apps_test:text', 'props' => ['text' => 'x']]],
      [['component' => 'mcp_apps_test:text', 'props' => ['text' => ['#markup' => 'Unsafe']]]],
      [['component' => 'mcp_apps_test:reference', 'props' => ['unknown' => []]]],
    ];
    foreach ($invalid as $badTree) {
      try {
        $composer->preview($id, $badTree);
        $this->fail('Invalid composition accepted.');
      }
      catch (\InvalidArgumentException) {
      }
    }
    $this->assertSame($tree, $this->container->get('mcp_apps_openui.sessions')->get($id)['tree']);
    try {
      $composer->asset($id, str_repeat('a', 64), 0);
      $this->fail('Unattached asset accepted.');
    }
    catch (\InvalidArgumentException) {
    }
    $this->setCurrentUser($this->createUser(['use mcp apps composer']));
    $this->expectException(\InvalidArgumentException::class);
    $composer->asset($id, $asset, 0);
  }

  /**
   * Tests permission boundaries.
   */
  public function testPermissionBoundaries(): void {
    $manager = $this->container->get('plugin.manager.tool');
    $provider = $this->container->get('plugin.manager.mcp_server.resource_provider')->createInstance('mcp_apps_composer');
    foreach ([[], ['use mcp apps composer']] as $permissions) {
      $account = $this->createUser($permissions);
      $this->setCurrentUser($account);
      $this->assertSame((bool) $permissions, $manager->createInstance('component_composer_open')->access());
      $this->assertSame((bool) $permissions, $provider->checkAccess('ui://drupal/component-composer', $account)->isAllowed());
      if ($permissions) {
        $result = $manager->createInstance('component_composer_open')->execute()->getResult();
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Component preview', $result->getMeta()['mcp']['ui']['title']);
        $this->assertSame([], $result->getMeta()['mcp']['ui']['tree']);
      }
    }
  }

}
