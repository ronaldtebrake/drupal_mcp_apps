<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders trusted SDCs with their real theme and attached libraries.
 */
final class PreviewRenderer {

  /**
   * Attached assets for the current preview.
   *
   * @var array
   */
  private array $assets = [];
  /**
   * Installed extension directories allowed for asset transport.
   *
   * @var array
   */
  private array $assetRoots = [];

  public function __construct(
    private readonly Composition $composition,
    private readonly RendererInterface $renderer,
    private readonly AssetResolverInterface $resolver,
    private readonly ThemeManagerInterface $themes,
    private readonly ThemeInitializationInterface $initialization,
    private readonly ThemeExtensionList $themeList,
    private readonly ModuleExtensionList $moduleList,
    private readonly RequestStack $requests,
  ) {}

  /**
   * Renders native components and their attached assets.
   */
  public function render(array $tree, string $theme): array {
    $previous = $this->themes->getActiveTheme();
    $this->assets = [];
    $this->composition->resetTransportImages();
    $this->assetRoots = [realpath(DRUPAL_ROOT . '/core'), realpath(DRUPAL_ROOT . '/libraries')];
    foreach ($this->moduleList->getList() + $this->themeList->getList() as $extension) {
      $this->assetRoots[] = realpath(DRUPAL_ROOT . '/' . $extension->getPath());
    }
    try {
      $active = $this->initialization->getActiveThemeByName($theme);
      $this->themes->setActiveTheme($active);
      $build = $this->composition->renderArray($tree);
      $build['#attached']['library'] = $active->getLibraries();
      $html = (string) $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
        return $this->renderer->render($build);
      });
      $attached = AttachedAssets::createFromRenderArray($build);
      $head = '';
      foreach ($this->resolver->getCssAssets($attached, FALSE) as $asset) {
        $head .= '<link rel="stylesheet" href="' . $this->asset($asset['data']) . '">';
      }
      [$header, $footer] = $this->resolver->getJsAssets($attached, FALSE);
      $scripts = '';
      foreach ($header + $footer as $asset) {
        if (($asset['type'] ?? '') === 'setting') {
          continue;
        }
        $scripts .= '<script src="' . $this->asset($asset['data']) . '"></script>';
      }
      $settings = json_encode($attached->getSettings(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
      $html = preg_replace_callback('/\b(src|poster)="([^"]+)"/', fn($match) => $match[1] . '="' . $this->asset(html_entity_decode($match[2])) . '"', $html);
      return [
        'html' => '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; img-src data:; font-src data:; style-src &#39;unsafe-inline&#39; data:; script-src &#39;unsafe-inline&#39; data:; connect-src &#39;none&#39;; base-uri &#39;none&#39;; form-action &#39;none&#39;"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}main{container-type:inline-size}</style>' . $head . '</head><body><main>' . $html . '</main><script>window.drupalSettings=' . $settings . ';</script>' . $scripts . '</body></html>',
        'assets' => $this->assets,
      ];
    }
    finally {
      $this->themes->setActiveTheme($previous);
    }
  }

  /**
   * Returns an attached preview asset.
   */
  private function asset(string $url, ?string $relative = NULL): string {
    if (str_starts_with($url, 'data:') || str_starts_with($url, '#')) {
      return $url;
    }
    $parsed = parse_url($url);
    if (isset($parsed['host']) && $parsed['host'] !== $this->requests->getCurrentRequest()?->getHost()) {
      throw new \InvalidArgumentException('External preview assets are not supported: ' . $url);
    }
    $path = rawurldecode($parsed['path'] ?? '');
    $absolute = str_starts_with($path, '/') || str_starts_with($path, 'core/') || str_starts_with($path, 'themes/') || str_starts_with($path, 'modules/') || str_starts_with($path, 'sites/');
    $file = realpath(($absolute || !$relative ? DRUPAL_ROOT : dirname($relative)) . '/' . ltrim($path, '/'));
    $extension = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
    $owned = $file && array_filter($this->assetRoots, static fn($root) => $root && str_starts_with($file, $root . '/'));
    $mimes = [
      'css' => 'text/css',
      'js' => 'text/javascript',
      'woff2' => 'font/woff2',
      'woff' => 'font/woff',
      'ttf' => 'font/ttf',
      'svg' => 'image/svg+xml',
      'png' => 'image/png',
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'webp' => 'image/webp',
      'avif' => 'image/avif',
      'gif' => 'image/gif',
    ];
    // Installed extensions can live outside the web root via Composer symlinks.
    if (!$file || (!$owned && !$this->composition->canTransportImage($file)) || !isset($mimes[$extension]) || !is_file($file) || filesize($file) > 6 * 1024 * 1024) {
      throw new \InvalidArgumentException('Unsupported or missing preview asset: ' . $url);
    }
    $id = hash('sha256', $file);
    if (isset($this->assets[$id])) {
      return 'mcp-asset:' . $id;
    }
    $bytes = file_get_contents($file);
    $this->assets[$id] = ['mime' => $mimes[$extension], 'bytes' => $bytes, 'urls' => []];
    if ($extension === 'css') {
      preg_match_all('/url\(\s*[\'"]?([^\)\'"\s]+)[\'"]?\s*\)|@import\s+[\'"]([^\'"\s]+)[\'"]/', $bytes, $matches, PREG_SET_ORDER);
      foreach ($matches as $match) {
        $target = $match[1] ?: ($match[2] ?? '');
        $this->assets[$id]['urls'][$target] = $this->asset($target, $file);
      }
    }
    if (count($this->assets) > 80 || array_sum(array_map(static fn($asset) => strlen($asset['bytes']), $this->assets)) > 20 * 1024 * 1024) {
      throw new \InvalidArgumentException('This preview exceeds the app asset budget.');
    }
    return 'mcp-asset:' . $id;
  }

}
