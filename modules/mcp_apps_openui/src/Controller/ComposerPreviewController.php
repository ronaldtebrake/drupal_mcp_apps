<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\mcp_apps\AppResourceBuilder;
use Drupal\mcp_apps_openui\Composer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Optional browser QA surface; the MCP App uses the host's MCP connection.
 */
final class ComposerPreviewController extends ControllerBase {

  public function __construct(
    private readonly AppResourceBuilder $resources,
    private readonly PluginManagerInterface $tools,
    private readonly CsrfTokenGenerator $csrf,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mcp_apps.resource_builder'), $container->get('plugin.manager.tool'), $container->get('csrf_token'));
  }

  /**
   * Returns the browser development preview.
   */
  public function page(): Response {
    $result = $this->invoke('component_composer_open', []);
    $resource = $this->resources->build('mcp_apps_openui', 'dist/composer.html', Composer::URI);
    $content = $resource->toResourceContents();
    $boot = json_encode([
      'result' => $result,
      'endpoint' => Url::fromRoute('mcp_apps_openui.browser_call')->toString(),
      'token' => $this->csrf->get('rest'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    return new Response(str_replace('</head>', '<script>window.__DRUPAL_BROWSER_PREVIEW__=' . $boot . ';</script></head>', $content['text']), 200, [
      'Cache-Control' => 'private, no-store',
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);
  }

  /**
   * Handles an allowlisted, CSRF-protected preview operation.
   */
  public function call(Request $request): JsonResponse {
    if (strlen($request->getContent()) > 80000) {
      return new JsonResponse(['error' => 'Preview request is too large.'], 413);
    }
    $input = json_decode($request->getContent(), TRUE);
    if (!is_array($input) || !is_array($input['arguments'] ?? [])) {
      return new JsonResponse(['error' => 'Use a JSON object with named arguments.'], 400);
    }
    $name = $input['name'] ?? '';
    if (!in_array($name, ['component_composer_preview', 'component_composer_asset', 'composer_media_search'], TRUE)) {
      return new JsonResponse(['error' => 'Unsupported preview operation.'], 400);
    }
    try {
      return new JsonResponse($this->invoke($name, $input['arguments'] ?? []), headers: [
        'Cache-Control' => 'private, no-store',
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400, ['Cache-Control' => 'private, no-store']);
    }
  }

  /**
   * Validates access and inputs before executing a Tool API plugin.
   */
  private function invoke(string $name, array $inputs): array {
    if (!$this->tools->hasDefinition($name)) {
      throw new \InvalidArgumentException('That preview operation is not enabled.');
    }
    $tool = $this->tools->createInstance($name);
    foreach ($inputs as $key => $value) {
      $tool->setInputValue($key, $value);
    }
    if (!$tool->access()) {
      throw new \InvalidArgumentException('Access denied.');
    }
    $violations = $tool->validateInputs();
    if ($violations->count()) {
      throw new \InvalidArgumentException('Invalid composer inputs.');
    }
    $result = $tool->execute()->getResult();
    if (!$result->isSuccess()) {
      throw new \InvalidArgumentException((string) $result->getMessage());
    }
    return [
      'structuredContent' => $result->getContextValues(),
      '_meta' => $result->getMeta()['mcp'] ?? [],
      'content' => [['type' => 'text', 'text' => (string) $result->getMessage()]],
    ];
  }

}
