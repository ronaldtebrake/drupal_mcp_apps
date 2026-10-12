<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_commerce_demo\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\mcp_apps\AppResourceBuilder;
use Drupal\mcp_apps_commerce_demo\Shop;
use Drupal\mcp_apps_openui\Composition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The ordinary Drupal shop and optional development transport.
 */
final class ShopController extends ControllerBase {

  public function __construct(
    private readonly Shop $shop,
    private readonly Composition $composition,
    private readonly RendererInterface $renderer,
    private readonly CsrfTokenGenerator $csrf,
    private readonly AppResourceBuilder $resources,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mcp_apps_commerce_demo.shop'), $container->get('mcp_apps_openui.composition'), $container->get('renderer'), $container->get('csrf_token'), $container->get('mcp_apps.resource_builder'));
  }

  /**
   * Renders the ordinary Drupal shopping page.
   */
  public function page(Request $request): array {
    $payload = $this->open($request);
    $tree = $this->composition->validate($payload['ui']['tree']);
    return [
      'status' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'mcp-apps-shop-status',
          'role' => 'alert',
        ],
      ],
      'shop' => [
        '#type' => 'container',
        '#attributes' => ['id' => 'mcp-apps-shop'],
        'components' => $this->composition->renderArray($tree),
      ],
      '#attached' => [
        'library' => ['mcp_apps_commerce_demo/website'],
        'drupalSettings' => [
          'mcpAppsCommerce' => [
            'boot' => $payload['ui'],
            'endpoint' => Url::fromRoute('mcp_apps_commerce_demo.action')->toString(),
            'token' => $this->csrf->get('rest'),
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Handles access-checked website actions.
   */
  public function action(Request $request): JsonResponse {
    try {
      $result = $this->callResult($request);
      $tree = $this->composition->validate($result['_meta']['ui']['tree']);
      $build = $this->composition->renderArray($tree);
      $html = (string) $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
        return $this->renderer->render($build);
      });
      return new JsonResponse([
        'ui' => $result['_meta']['ui'],
        'html' => $html,
      ], headers: ['Cache-Control' => 'private, no-store']);
    }
    catch (\InvalidArgumentException | \JsonException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400, ['Cache-Control' => 'private, no-store']);
    }
  }

  /**
   * Serves the app bundle through the optional development route.
   */
  public function app(Request $request): Response {
    $payload = $this->open($request);
    $resource = $this->resources->build('mcp_apps_commerce_demo', 'dist/shop.html', Shop::URI)->toResourceContents();
    $boot = json_encode([
      'result' => [
        'structuredContent' => ['data' => $payload['data']],
        '_meta' => ['ui' => $payload['ui']],
      ],
      'endpoint' => Url::fromRoute('mcp_apps_commerce_dev.call')->toString(),
      'token' => $this->csrf->get('rest'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    return new Response(str_replace('</head>', '<script>window.__DRUPAL_BROWSER_PREVIEW__=' . $boot . ';</script></head>', $resource['text']), 200, [
      'Cache-Control' => 'private, no-store',
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);
  }

  /**
   * Handles the optional development transport.
   */
  public function call(Request $request): JsonResponse {
    try {
      return new JsonResponse($this->callResult($request, TRUE), headers: ['Cache-Control' => 'private, no-store']);
    }
    catch (\InvalidArgumentException | \JsonException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400, ['Cache-Control' => 'private, no-store']);
    }
  }

  /**
   * Rejects operations outside the transport allowlist.
   */
  private function callResult(Request $request, bool $preview = FALSE): array {
    if (strlen($request->getContent()) > 80000) {
      throw new \InvalidArgumentException('Shop request is too large.');
    }
    $input = json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
    $allowed = [
      'commerce_shop_filter',
      'commerce_shop_cart',
      'commerce_shop_checkout',
    ];
    if ($preview) {
      $allowed = [
        ...$allowed,
        'component_composer_preview',
        'component_composer_asset',
      ];
    }
    if (!is_array($input) || !is_array($input['arguments'] ?? NULL) || !in_array($input['name'] ?? NULL, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Unsupported shop operation.');
    }
    return $this->shop->invoke($input['name'], $input['arguments']);
  }

  /**
   * Opens an account-bound shop session.
   */
  private function open(Request $request): array {
    try {
      $budget = $request->query->get('budget', 0);
      if (!is_numeric($budget)) {
        throw new \InvalidArgumentException('Enter a numeric budget.');
      }
      return $this->shop->open((string) $request->query->get('query', ''), (string) $request->query->get('category', 'all'), (float) $budget, (string) $request->query->get('mode', 'auto'));
    }
    catch (\InvalidArgumentException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
  }

}
