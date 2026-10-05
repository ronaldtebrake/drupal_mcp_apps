<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticated, read-only browser previews of the actual app bundles.
 */
final class DemoPreviewController extends ControllerBase {

  /**
   * Embeds accessible data; host-only actions remain disabled in previews.
   */
  public function preview(string $demo): Response {
    $result = match ($demo) {
      'media-picker' => (new MediaPicker())->open(),
      default => throw new \InvalidArgumentException('Unknown demo.'),
    };
    if ($result->isError) {
      return new Response('Demo unavailable for this account.', 403);
    }
    $html = file_get_contents(dirname(__DIR__, 2) . '/dist/' . $demo . '.html');
    if ($html === FALSE) {
      return new Response('Build the demos first.', 503);
    }
    $data = json_encode($result->jsonSerialize(), JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $nonce = base64_encode(random_bytes(18));
    $html = str_replace('</head>', '<script nonce="' . $nonce . '">globalThis.__DEMO_PREVIEW__=' . $data . ';</script></head>', $html);
    $html = str_replace('<script id="app-script">', '<script id="app-script" nonce="' . $nonce . '">', $html);
    return new Response($html, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'private, no-store',
      'X-Robots-Tag' => 'noindex',
      'Content-Security-Policy' => "default-src 'none'; script-src 'nonce-" . $nonce . "'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'self'; object-src 'none'",
    ]);
  }

}
