<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Functional;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\Entity\Media;
use Drupal\mcp_apps\Mcp\HeroWorkflow;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Drupal\mcp_apps\Mcp\PickerAccess;
use Drupal\mcp_server\Exception\McpAuthorizationDeniedException;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Mcp\Server\Transport\StdioTransport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises picker permissions and Drupal content access on an isolated site.
 *
 * @group mcp_apps
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class MediaPickerAccessTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mcp_apps', 'mcp_apps_test', 'text'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Permission grants cannot bypass draft, Media, or revision restrictions.
   */
  public function testAccessBoundaries(): void {
    $this->createMediaType('image', ['id' => 'image']);
    ob_start();
    require DRUPAL_ROOT . '/' . $this->container->get('extension.list.module')->getPath('mcp_apps') . '/scripts/seed_demo.php';
    ob_end_clean();
    $registry = $this->container->get('state')->get('mcp_apps.demo_data');
    $node_id = (int) array_key_first($registry['posts']);
    $media_id = (int) array_key_last($registry['media']);
    $node = Node::load($node_id);
    $original = $node->toArray();
    $picker = new MediaPicker();
    $workflow = new HeroWorkflow();
    $proxy = $this->container->get('current_user');
    $original_account = $proxy->getAccount();
    $content = ['access content', 'view media', 'bypass node access'];
    $denied = $this->drupalCreateUser($content);
    $reader = $this->drupalCreateUser([...$content, PickerAccess::OPEN]);
    $writer_only = $this->drupalCreateUser([...$content, PickerAccess::UPDATE]);
    $no_node_access = $this->drupalCreateUser(['access content', 'view media', PickerAccess::OPEN, PickerAccess::UPDATE]);
    $field_denied = $this->drupalCreateUser([
      ...$content,
      PickerAccess::OPEN,
      PickerAccess::UPDATE,
      'deny test hero field edit',
    ]);
    $editor = $this->drupalCreateUser([...$content, PickerAccess::OPEN, PickerAccess::UPDATE]);
    try {
      foreach ([new AnonymousUserSession(), $denied, $writer_only] as $account) {
        $proxy->setAccount($account);
        $result = $picker->open();
        $this->assertTrue($result->isError);
        $this->assertNull($result->structuredContent);
        $this->assertStringContainsString('Access denied', $result->content[0]->text);
        $this->assertTrue($workflow->save($node_id, $media_id, 'Denied change', 'unknown')->isError);
        try {
          $picker->resource();
          $this->fail('An unauthorized account received the HTML app resource.');
        }
        catch (McpAuthorizationDeniedException $exception) {
          $this->assertSame(403, $exception->httpStatus);
        }
      }
      $proxy->setAccount($reader);
      $result = $picker->open(node_id: $node_id);
      $this->assertFalse($result->isError);
      $post = array_values(array_filter($result->structuredContent['posts'], static fn(array $post): bool => $post['id'] === $node_id))[0];
      $this->assertFalse($post['can_update']);
      $this->assertSame('text/html;profile=mcp-app', $picker->resource()->mimeType);
      $this->assertTrue($workflow->save($node_id, $media_id, 'Denied change', $post['revision'])->isError);
      $proxy->setAccount($field_denied);
      $field_post = array_values(array_filter($workflow->posts(), static fn(array $post): bool => $post['id'] === $node_id))[0];
      $this->assertFalse($field_post['can_update']);
      $this->assertTrue($workflow->save($node_id, $media_id, 'Denied field change', $post['revision'])->isError);
      $proxy->setAccount($no_node_access);
      $this->assertTrue($picker->open(node_id: $node_id)->isError);
      $this->assertTrue($workflow->save($node_id, $media_id, 'Denied change', $post['revision'])->isError);
      $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
      $this->assertSame($original, Node::load($node_id)->toArray(), 'Denied calls leave all node fields and the revision unchanged.');
      $proxy->setAccount($editor);
      $result = $workflow->save($node_id, $media_id, 'An article-specific hero description', $post['revision']);
      $this->assertFalse($result->isError);
      $this->assertTrue($result->structuredContent['post']['can_update']);
      $this->assertTrue($workflow->save($node_id, $media_id, 'Stale change', $post['revision'])->isError);
      $saved = Node::load($node_id);
      $this->assertFalse($saved->isPublished());
      $this->assertNotSame($node->getRevisionId(), $saved->getRevisionId());
      $this->assertSame($original['title'], $saved->get('title')->getValue());
      $this->assertSame($original['body'], $saved->get('body')->getValue());
      $token = $result->structuredContent['post']['revision'];
      $media = Media::load($media_id);
      $media->setUnpublished()->save();
      $this->container->get('entity_type.manager')->getAccessControlHandler('media')->resetCache();
      $this->assertTrue($workflow->save($node_id, $media_id, 'Hidden Media', $token)->isError);
      $this->assertNotContains($media_id, array_column($picker->open()->structuredContent['media'], 'id'));
      $media->setPublished()->save();
      $saved->setPublished()->save();
      $this->assertTrue($workflow->save($node_id, $media_id, 'Published change', $token)->isError);
      $this->container->get('entity_type.manager')->getAccessControlHandler('media')->resetCache();
      $second_id = (int) array_key_last($registry['posts']);
      $second = array_values(array_filter($workflow->posts(), static fn(array $post): bool => $post['id'] === $second_id))[0];
      $arguments = [
        'node_id' => $second_id,
        'media_id' => $media_id,
        'alt' => 'Saved through the Tool API bridge',
        'revision' => $second['revision'],
      ];
      $wire = $this->runMcpSession($reader, $arguments);
      $this->assertTrue($wire[4]['result']['isError']);
      $this->assertStringContainsString('access denied', $wire[4]['result']['content'][0]['text']);
      $wire = $this->runMcpSession($editor, $arguments);
      $this->assertFalse($wire[4]['result']['isError'] ?? FALSE);
      $this->assertSame('media-picker-hero-saved', $wire[4]['result']['structuredContent']['app']);
      $this->assertSame('draft', $wire[4]['result']['structuredContent']['post']['status']);
      $this->assertSame('Saved through the Tool API bridge', $wire[4]['result']['structuredContent']['post']['alt']);
    }
    finally {
      $proxy->setAccount($original_account);
    }
    $wire = $this->runMcpSession($denied);
    $this->assertTrue($wire[2]['result']['isError']);
    $this->assertArrayHasKey('error', $wire[3]);
    $this->assertArrayNotHasKey('result', $wire[3]);
    $wire = $this->runMcpSession($reader);
    $this->assertFalse($wire[2]['result']['isError'] ?? FALSE);
    $this->assertSame('media-picker', $wire[2]['result']['structuredContent']['app']);
    $this->assertSame('text/html;profile=mcp-app', $wire[3]['result']['contents'][0]['mimeType']);
    $this->drupalGet('/admin/content/mcp-apps/media-picker');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogin($denied);
    $this->drupalGet('/admin/content/mcp-apps/media-picker');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogout();
    $this->drupalLogin($reader);
    $this->drupalGet('/admin/content/mcp-apps/media-picker');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('__DEMO_PREVIEW__');
  }

  /**
   * Exercises actual SDK discovery, tool calls and resource reads over MCP.
   */
  private function runMcpSession(AccountInterface $account, array $save_arguments = []): array {
    $input = fopen('php://memory', 'r+');
    $output = fopen('php://memory', 'r+');
    $requests = [
      [
        'id' => 1,
        'method' => 'initialize',
        'params' => [
          'protocolVersion' => '2024-11-05',
          'capabilities' => [],
          'clientInfo' => ['name' => 'permission-test', 'version' => '1'],
        ],
      ],
      ['method' => 'notifications/initialized'],
      ['id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'tool_api__media_picker_open', 'arguments' => []]],
      ['id' => 3, 'method' => 'resources/read', 'params' => ['uri' => MediaPicker::URI]],
    ];
    if ($save_arguments !== []) {
      $requests[] = [
        'id' => 4,
        'method' => 'tools/call',
        'params' => ['name' => 'tool_api__media_picker_save_hero', 'arguments' => $save_arguments],
      ];
    }
    foreach ($requests as $request) {
      fwrite($input, json_encode(['jsonrpc' => '2.0'] + $request, JSON_THROW_ON_ERROR) . "\n");
    }
    fwrite($input, "\n\n\n");
    rewind($input);
    $transport = new class($input, $output) extends StdioTransport {

      /**
       * Captured server responses.
       */
      public string $captured = '';

      /**
       * Constructs a transport retaining its output stream for assertions.
       */
      // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
      public function __construct(mixed $input, private readonly mixed $output) {
        parent::__construct($input, $output);
      }

      /**
       * {@inheritdoc}
       */
      public function close(): void {
        $this->captured = (string) stream_get_contents($this->output, offset: 0);
        parent::close();
      }

    };
    $switcher = $this->container->get('account_switcher');
    $switcher->switchTo($account);
    try {
      $this->container->get('mcp_server.server.factory')->create()->run($transport);
    }
    finally {
      $switcher->switchBack();
    }
    $responses = [];
    foreach (array_filter(explode("\n", $transport->captured)) as $line) {
      $response = json_decode($line, TRUE, flags: JSON_THROW_ON_ERROR);
      if (isset($response['id'])) {
        $responses[$response['id']] = $response;
      }
    }
    $this->assertArrayHasKey(2, $responses);
    $this->assertArrayHasKey(3, $responses);
    return $responses;
  }

}
