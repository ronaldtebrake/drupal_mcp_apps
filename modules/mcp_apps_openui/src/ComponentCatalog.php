<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui;

use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ComponentPluginManager;

/**
 * Describes installed SDCs without requiring Canvas.
 */
final class ComponentCatalog {

  public function __construct(
    private readonly ComponentPluginManager $components,
    private readonly ThemeHandlerInterface $themes,
  ) {}

  /**
   * Describes installed Single Directory Components.
   */
  public function list(?string $provider = NULL): array {
    $catalog = [];
    foreach ($this->components->getDefinitions() as $id => $definition) {
      if ($provider !== NULL && !str_starts_with($id, $provider . ':')) {
        continue;
      }
      try {
        $this->components->find($id);
        $schema = $definition['props'] ?? ['type' => 'object', 'properties' => []];
        $issue = $this->schemaIssue($schema, $schema);
        $catalog[$id] = [
          'id' => $id,
          'name' => $definition['name'] ?? $id,
          'description' => $definition['description'] ?? '',
          'props' => $schema,
          'slots' => $definition['slots'] ?? [],
          'provider' => $definition['provider'] ?? explode(':', $id)[0],
          'supported' => !$issue,
          'reason' => $issue,
        ];
      }
      catch (\Exception $e) {
        $catalog[$id] = ['id' => $id, 'supported' => FALSE, 'reason' => $e->getMessage()];
      }
    }
    ksort($catalog);
    return $catalog;
  }

  /**
   * Returns a supported component definition.
   */
  public function get(string $id): array {
    $entry = $this->list(explode(':', $id)[0])[$id] ?? NULL;
    if (!$entry || !$entry['supported']) {
      throw new \InvalidArgumentException($entry['reason'] ?? 'Select an installed SDC with JSON-compatible props.');
    }
    return $entry;
  }

  /**
   * Requires an installed preview theme.
   */
  public function validateTheme(string $theme): void {
    if (!$this->themes->themeExists($theme)) {
      throw new \InvalidArgumentException('Select an installed preview theme.');
    }
  }

  /**
   * Reports schemas that cannot cross the JSON boundary.
   */
  private function schemaIssue(array $schema, array $root): ?string {
    foreach ($schema as $key => $value) {
      if ($key === 'type' && array_filter((array) $value, static fn($type) => !in_array($type, [
        'string',
        'integer',
        'number',
        'boolean',
        'null',
        'array',
        'object',
      ], TRUE))) {
        return 'PHP-object props cannot be transported as JSON.';
      }
      if ($key === '$ref') {
        $target = $root;
        if (!is_string($value) || !str_starts_with($value, '#/')) {
          return 'Unresolved schema reference: ' . (string) $value;
        }
        foreach (explode('/', substr($value, 2)) as $part) {
          $part = str_replace(['~1', '~0'], ['/', '~'], $part);
          if (!is_array($target) || !array_key_exists($part, $target)) {
            return 'Unresolved schema reference: ' . $value;
          }
          $target = $target[$part];
        }
      }
      if (is_array($value) && ($issue = $this->schemaIssue($value, $root))) {
        return $issue;
      }
    }
    return NULL;
  }

}
