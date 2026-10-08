<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_demo;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\State\StateInterface;
use Drupal\mcp_apps_openui\Composition;

/**
 * Reuses the demo's access-checked Media selection and bounded thumbnails.
 */
final class MediaLibrary {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly Composition $composition,
    private readonly ImageFactory $images,
    private readonly StateInterface $state,
  ) {}

  /**
   * Returns accessible Media and bounded app-only thumbnails.
   */
  public function search(string $query = ''): array {
    $ids = $this->entities->getStorage('media')->getQuery()->accessCheck(TRUE)->condition('status', 1)->sort('mid', 'DESC');
    if ($query !== '') {
      $ids->condition('name', $query, 'CONTAINS');
    }
    $items = [];
    $thumbnails = [];
    $budget = 0;
    $registry = $this->state->get('mcp_apps.demo_data')['media'] ?? [];
    foreach ($this->entities->getStorage('media')->loadMultiple($ids->range(0, 24)->execute()) as $media) {
      try {
        $image = $this->composition->image((int) $media->id());
        $type = $this->entities->getStorage('media_type')->load($media->bundle());
        $field = $media->get($media->getSource()->getSourceFieldDefinition($type)->getName());
        $style = $this->entities->getStorage('image_style')->load('mcp_apps_picker');
        if (!$style) {
          continue;
        }
        $uri = $style->buildUri($field->entity->getFileUri());
        if (!is_file($uri) && !$style->createDerivative($field->entity->getFileUri(), $uri)) {
          continue;
        }
        $thumbnail = $this->images->get($uri);
        if (!$thumbnail->isValid() || !in_array($thumbnail->getMimeType(), [
          'image/jpeg',
          'image/png',
          'image/webp',
          'image/gif',
        ], TRUE) || filesize($uri) > 256 * 1024 || $budget + filesize($uri) > 2 * 1024 * 1024) {
          continue;
        }
        $bytes = file_get_contents($uri);
        $budget += strlen($bytes);
        $id = (int) $media->id();
        $credit = ($registry[$id]['uuid'] ?? '') === $media->uuid() ? ($registry[$id]['credit'] ?? '') : '';
        $items[] = ['id' => $id, 'name' => (string) $media->label(), 'alt' => $image['alt'], 'credit' => $credit];
        $thumbnails[$id] = 'data:' . $thumbnail->getMimeType() . ';base64,' . base64_encode($bytes);
      }
      catch (\InvalidArgumentException) {
        continue;
      }
    }
    return ['data' => ['media' => $items], 'ui' => ['thumbnails' => $thumbnails]];
  }

}
