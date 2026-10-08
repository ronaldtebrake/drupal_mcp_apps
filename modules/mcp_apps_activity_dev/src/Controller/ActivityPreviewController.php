<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_dev\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mcp_apps\AppResourceBuilder;
use Drupal\mcp_apps_activity_demo\Dashboard;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Runs the activity app bundle with a local development transport.
 */
final class ActivityPreviewController extends ControllerBase {

  public function __construct(
    private readonly Dashboard $dashboard,
    private readonly AppResourceBuilder $resources,
    private readonly CsrfTokenGenerator $csrf,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mcp_apps_activity_demo.dashboard'), $container->get('mcp_apps.resource_builder'), $container->get('csrf_token'));
  }

  /**
   * Loads the same bundle that resources/read returns to an MCP host.
   */
  public function app(Request $request): Response {
    try {
      $result = $this->dashboard->invoke('activity_dashboard_open', [
        'view' => $request->query->get('view', 'dashboard'),
        'days' => filter_var($request->query->get('days', 30), FILTER_VALIDATE_INT),
        'section' => $request->query->get('section', 'all'),
      ]);
    }
    catch (\InvalidArgumentException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
    $resource = $this->resources->build('mcp_apps_activity_demo', 'dist/activity.html', Dashboard::URI)->toResourceContents();
    $boot = json_encode([
      'result' => $result,
      'endpoint' => Url::fromRoute('mcp_apps_activity_dev.call')->toString(),
      'token' => $this->csrf->get('rest'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    return new Response(str_replace('</head>', '<script>window.__DRUPAL_BROWSER_PREVIEW__=' . $boot . ';</script></head>', $resource['text']), 200, [
      'Cache-Control' => 'private, no-store',
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);
  }

  /**
   * Exposes only the three read-only operations needed by this app.
   */
  public function call(Request $request): JsonResponse {
    if (strlen($request->getContent()) > 80000) {
      return new JsonResponse(['error' => 'Activity request is too large.'], 413);
    }
    $input = json_decode($request->getContent(), TRUE);
    $allowed = ['activity_dashboard_filter', 'component_composer_preview', 'component_composer_asset'];
    if (!is_array($input) || !is_array($input['arguments'] ?? NULL) || !in_array($input['name'] ?? NULL, $allowed, TRUE)) {
      return new JsonResponse(['error' => 'Unsupported activity request.'], 400);
    }
    try {
      return new JsonResponse($this->dashboard->invoke($input['name'], $input['arguments']), headers: ['Cache-Control' => 'private, no-store']);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400, ['Cache-Control' => 'private, no-store']);
    }
  }

}
