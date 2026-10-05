<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Mcp;

use Drupal\Core\Utility\FiberResumeType;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Extension\Apps\UiResourceContentMeta;
use Mcp\Schema\Extension\Apps\UiResourceCsp;
use Mcp\Schema\Result\CallToolResult;

/**
 * SDK result and resource helpers for the Media picker app.
 */
final class DemoSupport {

  /**
   * Completes Drupal entity-loading Fibers inside the SDK operation.
   */
  public static function respond(callable $operation): CallToolResult {
    try {
      if (\Fiber::getCurrent() !== NULL) {
        $fiber = new \Fiber($operation);
        $yielded = $fiber->start();
        while ($fiber->isSuspended()) {
          if ($yielded !== NULL && $yielded !== FiberResumeType::Immediate) {
            throw new \RuntimeException('Unexpected asynchronous Drupal operation.');
          }
          $yielded = $fiber->resume();
        }
        $data = $fiber->getReturn();
      }
      else {
        $data = $operation();
      }
      // Presentation-only data travels to the app outside model-visible data.
      $meta = $data['_app_meta'] ?? NULL;
      unset($data['_app_meta']);
      return new CallToolResult([
        new TextContent($data['message']),
        new TextContent(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
      ], structuredContent: $data, meta: $meta);
    }
    catch (\InvalidArgumentException $exception) {
      return CallToolResult::error([new TextContent($exception->getMessage())]);
    }
  }

  /**
   * Serves a self-contained app bundle using native SDK Apps metadata.
   */
  public static function resource(string $demo): TextResourceContents {
    $path = \Drupal::service('extension.list.module')->getPath('mcp_apps') . '/dist/' . $demo . '.html';
    $html = file_get_contents($path);
    if ($html === FALSE) {
      throw new \RuntimeException('Build the MCP App first.');
    }
    return new TextResourceContents('ui://drupal/' . $demo, McpApps::MIME_TYPE, $html, [
      'ui' => new UiResourceContentMeta(
        csp: new UiResourceCsp(resourceDomains: [self::origin()]),
        prefersBorder: FALSE,
      ),
    ]);
  }

  /**
   * Uses the MCP request's external Drupal origin.
   */
  public static function origin(): string {
    return \Drupal::request()->getSchemeAndHttpHost();
  }

}
