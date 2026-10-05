<?php

declare(strict_types=1);

namespace Drupal\mcp_apps;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\image\Entity\ImageStyle;
use Drupal\mcp_apps\Mcp\HeroWorkflow;
use Drupal\node\NodeInterface;

/**
 * Defines the article content and hero presentation shared by site and app.
 */
final class HeroArticle {

  public const IMAGE_STYLE = 'mcp_apps_hero';

  /**
   * Reads the same article text for both renderers.
   */
  public static function content(NodeInterface $node): array {
    return [
      'title' => $node->get('title')->access('view') ? $node->label() : '',
      'summary' => $node->hasField('body') && $node->get('body')->access('view') ? trim(strip_tags((string) ($node->get('body')->summary ?: $node->get('body')->value))) : '',
      'byline' => 'Editorial team',
      'date' => $node->get('created')->access('view') ? gmdate('F j, Y', (int) $node->getCreatedTime()) : '',
    ];
  }

  /**
   * Renders a demo article using its own alt text and the shared image style.
   */
  public static function build(NodeInterface $node): array {
    $build = [
      '#theme' => 'mcp_apps_article',
      '#article' => self::content($node),
      '#hero' => NULL,
      '#attached' => ['library' => ['mcp_apps/article']],
    ];
    $cache = CacheableMetadata::createFromObject($node)->addCacheContexts(['user']);
    $field = $node->get(HeroWorkflow::MEDIA_FIELD);
    $media = $field->entity;
    if ($field->access('view') && $media !== NULL && $media->access('view')) {
      $cache->addCacheableDependency($media);
      $type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media->bundle());
      $definition = $media->getSource()->getSourceFieldDefinition($type);
      if ($definition !== NULL && $definition->getType() === 'image') {
        $image = $media->get($definition->getName());
        $file = $image->entity;
        $style = ImageStyle::load(self::IMAGE_STYLE);
        if ($image->access('view') && $file !== NULL && $file->access('view') && str_starts_with($file->getFileUri(), 'public://') && $style !== NULL) {
          $cache->addCacheableDependency($file)->addCacheableDependency($style);
          $alt_field = $node->get(HeroWorkflow::ALT_FIELD);
          $build['#hero'] = [
            '#theme' => 'image_style',
            '#style_name' => self::IMAGE_STYLE,
            '#uri' => $file->getFileUri(),
            '#alt' => $alt_field->access('view') ? (string) $alt_field->value : '',
            '#width' => (int) $image->width,
            '#height' => (int) $image->height,
          ];
        }
      }
    }
    $cache->applyTo($build);
    return $build;
  }

}
