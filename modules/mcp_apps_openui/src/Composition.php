<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Component\Exception\InvalidComponentException;
use JsonSchema\Validator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Theme\Component\ComponentValidator;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\media\MediaInterface;

/**
 * Validates plain component data and builds trusted Drupal render arrays.
 */
final class Composition {

  /**
   * Access-checked image paths for the current render.
   *
   * @var array
   */
  private array $imageFiles = [];

  public function __construct(
    private readonly ComponentCatalog $catalog,
    private readonly ComponentPluginManager $components,
    private readonly ComponentValidator $validator,
    private readonly EntityTypeManagerInterface $entities,
    private readonly FileUrlGeneratorInterface $urls,
    private readonly FileSystemInterface $files,
  ) {}

  /**
   * Validates a JSON-compatible component tree.
   */
  public function validate(array $tree, int $depth = 0, int &$count = 0): array {
    if ($depth > 12 || !array_is_list($tree)) {
      throw new \InvalidArgumentException('Use an ordered component list with at most 12 nesting levels.');
    }
    $result = [];
    foreach ($tree as $node) {
      if (++$count > 100 || !is_array($node) || !is_string($node['component'] ?? NULL)) {
        throw new \InvalidArgumentException('Use at most 100 installed components.');
      }
      $id = $node['component'];
      $entry = $this->catalog->get($id);
      $props = $node['props'] ?? [];
      $slots = $node['slots'] ?? [];
      if (!is_array($props) || !is_array($slots) || array_diff(array_keys($props), array_keys($entry['props']['properties'] ?? [])) || array_diff(array_keys($slots), array_keys($entry['slots']))) {
        throw new \InvalidArgumentException('Unknown props or slots for ' . $id . '.');
      }
      foreach ($entry['props']['properties'] ?? [] as $name => $schema) {
        if (!array_key_exists($name, $props)) {
          $default = $schema['examples'][0] ?? $schema['default'] ?? NULL;
          if ($default !== NULL && in_array($name, $entry['props']['required'] ?? [], TRUE)) {
            $props[$name] = $default;
          }
        }
      }
      $resolved = $props;
      foreach ($props as $name => $value) {
        if (is_array($value) && array_key_exists('media_id', $value)) {
          if (array_keys($value) !== ['media_id'] || !is_int($value['media_id']) || $value['media_id'] <= 0) {
            throw new \InvalidArgumentException('Use a positive integer Media ID.');
          }
          $schema = $entry['props']['properties'][$name];
          if (($schema['id'] ?? $schema['$ref'] ?? '') !== 'json-schema-definitions://canvas.module/image') {
            throw new \InvalidArgumentException('Media references require an image prop.');
          }
          $resolved[$name] = $this->image((int) $value['media_id']);
        }
        $this->checkSafeValue($resolved[$name]);
      }
      // Core allows deferred render-array props; our JSON boundary does not.
      $jsonValidator = new Validator();
      $jsonProps = Validator::arrayToObjectRecursive($resolved);
      $jsonValidator->validate($jsonProps, Validator::arrayToObjectRecursive($entry['props']));
      if (!$jsonValidator->isValid()) {
        throw new \InvalidArgumentException($id . ': ' . implode('; ', array_map(static fn($error) => $error['property'] . ' ' . $error['message'], $jsonValidator->getErrors())));
      }
      try {
        $this->validator->validateProps($resolved, $this->components->find($id));
      }
      catch (InvalidComponentException $e) {
        throw new \InvalidArgumentException($e->getMessage(), previous: $e);
      }
      foreach ($entry['props']['required'] ?? [] as $required) {
        if (!array_key_exists($required, $resolved)) {
          throw new \InvalidArgumentException('Missing required prop ' . $id . '/' . $required . '.');
        }
      }
      $children = [];
      foreach ($slots as $name => $nodes) {
        if (!is_array($nodes)) {
          throw new \InvalidArgumentException('Slots contain component lists.');
        }
        $children[$name] = $this->validate($nodes, $depth + 1, $count);
      }
      $result[] = ['component' => $id, 'props' => $props, 'slots' => $children];
    }
    return $result;
  }

  /**
   * Builds native component render arrays.
   */
  public function renderArray(array $tree): array {
    $build = [];
    foreach ($tree as $node) {
      $props = $node['props'];
      foreach ($props as &$value) {
        if (is_array($value) && isset($value['media_id'])) {
          $value = $this->image((int) $value['media_id']);
        }
      }
      unset($value);
      $slots = [];
      foreach ($node['slots'] as $name => $children) {
        $slots[$name] = $this->renderArray($children);
      }
      $build[] = ['#type' => 'component', '#component' => $node['component'], '#props' => $props, '#slots' => $slots];
    }
    return $build;
  }

  /**
   * Resolves an accessible public image Media reference.
   */
  public function image(int $id): array {
    $media = $this->entities->getStorage('media')->load($id);
    if (!$media instanceof MediaInterface || !$media->access('view') || !$media->get('name')->access('view')) {
      throw new \InvalidArgumentException('That image is not accessible.');
    }
    $type = $this->entities->getStorage('media_type')->load($media->bundle());
    $definition = $media->getSource()->getSourceFieldDefinition($type);
    if (!$definition || $definition->getType() !== 'image') {
      throw new \InvalidArgumentException('Select image Media.');
    }
    $field = $media->get($definition->getName());
    if (!$field->access('view') || $field->isEmpty() || !$field->entity->access('view') || !str_starts_with($field->entity->getFileUri(), 'public://')) {
      throw new \InvalidArgumentException('Select an accessible public image.');
    }
    $path = $this->files->realpath($field->entity->getFileUri());
    if (!$path) {
      throw new \InvalidArgumentException('That image file is missing.');
    }
    $this->imageFiles[$path] = TRUE;
    return [
      'src' => $this->urls->generateAbsoluteString($field->entity->getFileUri()),
      'alt' => (string) $field->alt,
      'width' => (int) $field->width,
      'height' => (int) $field->height,
    ];
  }

  /**
   * Checks whether this image passed Media access checks.
   */
  public function canTransportImage(string $path): bool {
    return isset($this->imageFiles[$path]);
  }

  /**
   * Clears image approvals before rendering a composition.
   */
  public function resetTransportImages(): void {
    $this->imageFiles = [];
  }

  /**
   * Rejects unsafe values and Drupal render arrays.
   */
  private function checkSafeValue(mixed $value): void {
    if (is_array($value) && array_filter(array_keys($value), static fn($key) => is_string($key) && str_starts_with($key, '#'))) {
      throw new \InvalidArgumentException('Render arrays are not accepted as props.');
    }
    if (is_array($value)) {
      foreach ($value as $child) {
        $this->checkSafeValue($child);
      }
    }
    if (is_string($value) && (preg_match('/^(?:javascript|data|vbscript):/i', preg_replace('/[\x00-\x20]/', '', $value)) || $value !== Xss::filter($value))) {
      throw new \InvalidArgumentException('Props must contain safe text and URLs.');
    }
  }

}
