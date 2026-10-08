<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests Drupal MCP Apps integration.
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class FoundationTest extends KernelTestBase {
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
    'mcp_apps',
    'mcp_apps_test',
    'mcp_apps_other_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'mcp_server']);
    $plugins = [];
    foreach (['mcp_apps_test', 'mcp_apps_other_test'] as $id) {
      $plugins[$id] = ['id' => $id, 'enabled' => TRUE, 'configuration' => []];
    }
    $this->config('mcp_server.resource_providers')->set('plugins', $plugins)->save();
  }

  /**
   * Tests independent apps and account isolation.
   */
  public function testIndependentAppsAndAccountIsolation(): void {
    $first = $this->createUser(['access mcp server', 'use test apps']);
    $second = $this->createUser(['access mcp server', 'use test apps']);
    foreach ([$first, $second] as $account) {
      $this->setCurrentUser($account);
      $session = $this->initialize();
      $tools = array_column($this->request('tools/list', [], $session)['body']['result']['tools'], NULL, 'name');
      foreach (['mcp_apps_test', 'mcp_apps_other_test'] as $module) {
        $uri = 'ui://' . $module . '/app';
        $this->assertSame($uri, $tools[$module . '_open']['_meta']['ui']['resourceUri']);
        $result = $this->request('resources/read', ['uri' => $uri], $session)['body']['result']['contents'][0];
        $this->assertSame('text/html;profile=mcp-app', $result['mimeType']);
        $this->assertSame([], $result['_meta']['ui']['csp']['connectDomains']);
        $this->assertStringContainsString('account:' . $account->id(), $result['text']);
      }
    }
    $this->setCurrentUser($this->createUser(['access mcp server']));
    $denied = $this->request('resources/read', ['uri' => 'ui://mcp_apps_test/app'], $this->initialize())['body'];
    $this->assertArrayHasKey('error', $denied);
    $this->assertArrayNotHasKey('result', $denied);
  }

  /**
   * Initializes an MCP session for the current test account.
   */
  private function initialize(): string {
    $reply = $this->request('initialize', [
      'protocolVersion' => '2025-06-18',
      'capabilities' => [],
      'clientInfo' => ['name' => 'test', 'version' => '1'],
    ]);
    $this->assertArrayHasKey('io.modelcontextprotocol/ui', $reply['body']['result']['capabilities']['extensions']);
    return $reply['session'];
  }

  /**
   * Exercises the native HTTP MCP transport.
   */
  private function request(string $method, array $params, ?string $session = NULL): array {
    $stack = $this->container->get('request_stack');
    while ($stack->getCurrentRequest()) {
      $stack->pop();
    }
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
    if ($session) {
      $server['HTTP_MCP_SESSION_ID'] = $session;
    }
    $request = Request::create('/mcp', 'POST', [], [], [], $server, json_encode([
      'jsonrpc' => '2.0',
      'id' => 1,
      'method' => $method,
      'params' => $params,
    ]));
    $response = $this->container->get('http_kernel')->handle($request);
    return [
      'session' => $response->headers->get('Mcp-Session-Id'),
      'body' => json_decode((string) $response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR),
    ];
  }

}
