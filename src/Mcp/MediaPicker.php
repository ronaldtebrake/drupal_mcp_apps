<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Mcp;

use Drupal\media\MediaInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\mcp_apps\HeroArticle;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;

/**
 * Access-checked Drupal Media search with a visual selection app.
 */
final class MediaPicker {

  public const URI = 'ui://drupal/media-picker';

  /**
   * Searches Media entities; the UI returns a selection to the chat.
   */
  #[McpTool(
    name: 'media_picker_open',
    title: 'Drupal Media Picker',
    description: 'Open an article hero-image workflow with a live Olivero-style article preview. Pass node_id for a specific demo article, or article_title to find it by title. Choose an image, review its appearance and post-specific alt text, then explicitly save a draft revision. Opening is read-only. Sample stock photos are labelled.',
    annotations: new ToolAnnotations(readOnlyHint: TRUE, openWorldHint: FALSE),
    meta: ['ui' => ['resourceUri' => self::URI]],
  )]
  public function open(#[Schema(maxLength: 200)] string $query = '', int $node_id = 0, #[Schema(maxLength: 200)] string $article_title = ''): CallToolResult {
    return DemoSupport::respond(function () use ($query, $node_id, $article_title): array {
      PickerAccess::requireAccess();
      if ($node_id < 0 || mb_strlen($query) > 200 || mb_strlen($article_title) > 200) {
        throw new \InvalidArgumentException('Search must be at most 200 characters.');
      }
      $storage = \Drupal::entityTypeManager()->getStorage('media');
      $ids = $storage->getQuery()->accessCheck(TRUE)->sort('created', 'DESC')->range(0, 201)->execute();
      $items = [];
      $posts = (new HeroWorkflow())->posts();
      $hero_ids = array_column(array_filter(array_column($posts, 'hero')), 'id');
      $hero_media = [];
      $thumbnails = [];
      $hero_thumbnails = [];
      $thumbnail_bytes = 0;
      $needle = mb_strtolower(trim($query));
      $registry = \Drupal::state()->get('mcp_apps.demo_data', []);
      foreach ($storage->loadMultiple(array_slice($ids, 0, 200)) as $media) {
        if (!$media instanceof MediaInterface || !$media->access('view') || !$media->get('name')->access('view')) {
          continue;
        }
        $media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media->bundle());
        $definition = $media->getSource()->getSourceFieldDefinition($media_type);
        if ($definition === NULL || $definition->getType() !== 'image') {
          continue;
        }
        $field = $media->get($definition->getName());
        if (!$field->access('view') || $field->isEmpty()) {
          continue;
        }
        $file = $field->entity;
        if ($file === NULL || !$file->access('view') || !str_starts_with($file->getFileUri(), 'public://') || !str_starts_with($file->getMimeType(), 'image/')) {
          continue;
        }
        $alt = (string) $field->alt;
        $matches = $needle === '' || str_contains(mb_strtolower($media->label() . ' ' . $alt), $needle);
        if (!$matches && !in_array((int) $media->id(), $hero_ids, TRUE)) {
          continue;
        }
        $source = $registry['media'][$media->id()] ?? [];
        $sample = ($source['uuid'] ?? NULL) === $media->uuid();
        $item = [
          'id' => (int) $media->id(),
          'name' => $media->label(),
          'thumbnail' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
          'hero_thumbnail' => ImageStyle::load(HeroArticle::IMAGE_STYLE)?->buildUrl($file->getFileUri()),
          'alt' => $alt,
          'width' => (int) $field->width,
          'height' => (int) $field->height,
          'filename' => $file->getFilename(),
          'demo' => $sample,
          'credit' => $sample ? ($source['credit'] ?? '') : '',
          'source' => $sample ? ($source['source'] ?? '') : '',
        ];
        if ($matches) {
          $items[] = $item;
        }
        if (in_array((int) $media->id(), $hero_ids, TRUE)) {
          $hero_media[] = $item;
        }
        // Local development URLs may be unreachable inside a remote host.
        // Send bounded thumbnails over MCP instead of requiring browser access.
        if ($thumbnail_bytes < 4 * 1024 * 1024) {
          $thumbnail = $this->thumbnail($file->getFileUri());
          if ($thumbnail !== NULL) {
            $thumbnails[(int) $media->id()] = $thumbnail;
            $thumbnail_bytes += strlen($thumbnail);
          }
          $hero_thumbnail = $this->thumbnail($file->getFileUri(), HeroArticle::IMAGE_STYLE);
          if ($hero_thumbnail !== NULL && $thumbnail_bytes + strlen($hero_thumbnail) <= 4 * 1024 * 1024) {
            $hero_thumbnails[(int) $media->id()] = $hero_thumbnail;
            $thumbnail_bytes += strlen($hero_thumbnail);
          }
        }
      }
      if ($article_title !== '' && $node_id === 0) {
        $matches = array_values(array_filter($posts, static fn(array $post): bool => str_contains(mb_strtolower($post['title']), mb_strtolower(trim($article_title)))));
        if (count($matches) !== 1) {
          throw new \InvalidArgumentException('Choose an article ID: the title must match exactly one accessible demo article.');
        }
        $node_id = $matches[0]['id'];
      }
      if ($node_id > 0 && !in_array($node_id, array_column($posts, 'id'), TRUE)) {
        throw new \InvalidArgumentException('That post is not available in this demo workflow.');
      }
      return [
        'app' => 'media-picker',
        'origin' => DemoSupport::origin(),
        'query' => $query,
        'media' => $items,
        'hero_media' => $hero_media,
        'posts' => $posts,
        'node_id' => $node_id,
        'limited' => count($ids) > 200,
        '_app_meta' => [
          'drupal/media-picker' => [
            'thumbnails' => $thumbnails,
            'heroThumbnails' => $hero_thumbnails,
          ],
        ],
        'message' => sprintf('Found %d accessible Drupal images%s. Choose a draft post and review its hero change before saving, or send the media selection to chat. Opening changes no content.', count($items), $query !== '' ? ' matching "' . $query . '"' : ''),
      ];
    });
  }

  /**
   * Embeds a Drupal-generated derivative of an already access-checked image.
   */
  private function thumbnail(string $uri, string $style_name = 'mcp_apps_picker'): ?string {
    $style = ImageStyle::load($style_name);
    if ($style === NULL) {
      return NULL;
    }
    $derivative = $style->buildUri($uri);
    if (!is_file($derivative) && !$style->createDerivative($uri, $derivative)) {
      return NULL;
    }
    $image = \Drupal::service('image.factory')->get($derivative);
    if (!$image->isValid() || !in_array($image->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], TRUE)) {
      return NULL;
    }
    $size = filesize($derivative);
    if ($size === FALSE || $size > 512 * 1024) {
      return NULL;
    }
    $bytes = file_get_contents($derivative);
    return $bytes === FALSE ? NULL : 'data:' . $image->getMimeType() . ';base64,' . base64_encode($bytes);
  }

  /**
   * Returns the bundled official-SDK app.
   */
  #[McpResource(uri: self::URI, name: 'drupal-media-picker', title: 'Drupal Media Picker', mimeType: McpApps::MIME_TYPE, meta: ['ui' => new \stdClass()])]
  public function resource(): TextResourceContents {
    PickerAccess::requireAccess();
    $resource = DemoSupport::resource('media-picker');
    // Also bundle thumbnails in the resource for hosts that omit result _meta.
    // The same current-account entity/file access checks apply here.
    $result = $this->open();
    $thumbnails = $result->meta['drupal/media-picker']['thumbnails'] ?? [];
    $data = json_encode($thumbnails, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $heroes = json_encode($result->meta['drupal/media-picker']['heroThumbnails'] ?? [], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $html = str_replace('</head>', '<script>globalThis.__MEDIA_THUMBNAILS__=' . $data . ';globalThis.__MEDIA_HERO_THUMBNAILS__=' . $heroes . ';</script></head>', $resource->text);
    return new TextResourceContents(self::URI, McpApps::MIME_TYPE, $html, $resource->meta);
  }

}
