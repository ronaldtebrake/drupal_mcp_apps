<?php

/**
 * @file
 * Verifies that node rendering and the MCP App use the same hero crop and text.
 */

declare(strict_types=1);

use Drupal\mcp_apps\HeroArticle;
use Drupal\mcp_apps\Mcp\HeroWorkflow;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Drupal\image\Entity\ImageStyle;
use Drupal\user\Entity\User;

$check = static function (bool $value, string $message): void {
  if (!$value) {
    throw new RuntimeException($message);
  }
  print 'PASS: ' . $message . PHP_EOL;
};
$account = \Drupal::currentUser()->getAccount();
try {
  \Drupal::currentUser()->setAccount(User::load(1));
  $result = (new MediaPicker())->open(article_title: 'weekend of discovery');
  $node = \Drupal::entityTypeManager()->getStorage('node')->load($result->structuredContent['node_id']);
  $post = array_values(array_filter($result->structuredContent['posts'], static fn(array $item): bool => $item['id'] === (int) $node->id()))[0];
  $view = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'full');
  $html = (string) \Drupal::service('renderer')->renderRoot($view);
  $check(str_contains($html, 'mcp-article-title') && str_contains($html, htmlspecialchars($post['title'])), 'Full node renders the shared article layout');
  $check(str_contains($html, htmlspecialchars($post['summary'])), 'Site and app expose the same article text');
  $check(str_contains($html, 'styles/mcp_apps_hero/public/'), 'Website uses the shared hero image style');
  $check(str_contains($html, 'alt="' . htmlspecialchars($post['alt']) . '"'), 'Website uses article-specific alt text');
  $check(in_array('mcp_apps/article', $view['#attached']['library'] ?? [], TRUE), 'Website attaches the shared article CSS');
  $build = HeroArticle::build($node);
  $check(in_array('media:' . $post['hero']['id'], $build['#cache']['tags'], TRUE), 'Hero render cache tracks the selected Media');
  $media = $node->get(HeroWorkflow::MEDIA_FIELD)->entity;
  $type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media->bundle());
  $field_name = $media->getSource()->getSourceFieldDefinition($type)->getName();
  $style = ImageStyle::load(HeroArticle::IMAGE_STYLE);
  $uri = $style->buildUri($media->get($field_name)->entity->getFileUri());
  $image = \Drupal::service('image.factory')->get($uri);
  $check($image->getWidth() === 1100 && $image->getHeight() === 500, 'Hero is cropped to 1100 × 500');
  $inline = $result->meta['drupal/media-picker']['heroThumbnails'][$post['hero']['id']];
  $bytes = base64_decode(explode(',', $inline, 2)[1], TRUE);
  $check($bytes === file_get_contents($uri), 'App and website use byte-identical Drupal hero derivatives');
  $sample_id = array_key_first(\Drupal::state()->get('mcp_apps.demo_data')['nodes']);
  $other = \Drupal::entityTypeManager()->getStorage('node')->load($sample_id);
  $other_view = \Drupal::entityTypeManager()->getViewBuilder('node')->view($other, 'full');
  $other_html = (string) \Drupal::service('renderer')->renderRoot($other_view);
  $check(!str_contains($other_html, 'mcp-article-page'), 'Other content types retain their existing presentation');
}
finally {
  \Drupal::currentUser()->setAccount($account);
}
