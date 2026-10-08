<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Account-bound, read-only OpenUI preview sessions.
 */
final class Composer {
  public const URI = 'ui://drupal/component-composer';

  public function __construct(
    private readonly ComponentCatalog $catalog,
    private readonly Composition $composition,
    private readonly PreviewRenderer $renderer,
    private readonly ComposerSessions $sessions,
    private readonly ConfigFactoryInterface $config,
    private readonly ModuleHandlerInterface $modules,
  ) {}

  /**
   * Opens an account-bound composer session.
   */
  public function open(string $title, string $theme, string $program): array {
    $theme = $theme ?: $this->config->get('mcp_apps_openui.settings')->get('theme') ?: $this->config->get('system.theme')->get('default');
    $this->catalog->validateTheme($theme);
    $data = [
      'title' => $title,
      'theme' => $theme,
      'program' => $program,
      'tree' => [],
      'assets' => [],
      'revision' => 0,
    ];
    $this->modules->alter('mcp_apps_composer_defaults', $data);
    $id = $this->sessions->create($data);
    return [
      'data' => [
        'session_id' => $id,
        'instructions' => 'Use Page([DrupalComponent(componentId, props, namedSlots)]). Props are JSON objects; slots map names to ordered DrupalComponent lists. Existing image Media use {media_id: ID}. Generate only installed components from sdc_component_catalog. No JavaScript, Query or Mutation expressions. Preview does not save.',
      ],
      'ui' => $data + ['session_id' => $id, 'catalog' => $this->catalog->list()],
    ];
  }

  /**
   * Validates and renders a composition without saving content.
   */
  public function preview(string $id, array $tree): array {
    return $this->sessions->synchronized($id, function (array $session) use ($id, $tree) {
      $tree = $this->composition->validate($tree);
      $rendered = $this->renderer->render($tree, $session['theme']);
      $session['tree'] = $tree;
      $session['assets'] = $rendered['assets'];
      $session['revision']++;
      $this->sessions->put($id, $session);
      return [
        'data' => ['session_id' => $id, 'revision' => $session['revision'], 'valid' => TRUE],
        'ui' => [
          'html' => $rendered['html'],
          'tree' => $tree,
          'assets' => array_map(static fn($asset) => [
            'mime' => $asset['mime'],
            'size' => strlen($asset['bytes']),
            'urls' => $asset['urls'],
          ], $rendered['assets']),
        ],
      ];
    });
  }

  /**
   * Returns an attached preview asset.
   */
  public function asset(string $id, string $assetId, int $offset): array {
    $session = $this->sessions->get($id);
    $asset = $session['assets'][$assetId] ?? NULL;
    if (!$asset || $offset < 0 || $offset > strlen($asset['bytes'])) {
      throw new \InvalidArgumentException('That preview asset is not available.');
    }
    $chunk = substr($asset['bytes'], $offset, 196608);
    return ['data' => ['bytes' => strlen($chunk)], 'ui' => ['base64' => base64_encode($chunk)]];
  }

}
