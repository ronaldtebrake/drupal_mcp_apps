<?php

/**
 * @file
 * Seeds UUID-tracked sample Media and article drafts.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\image\Entity\ImageStyle;

$state_key = 'mcp_apps.demo_data';
$registry = \Drupal::state()->get($state_key, [
  'media' => [],
  'files' => [],
  'config' => [],
]);
$record = static function () use (&$registry, $state_key): void {
  \Drupal::state()->set($state_key, $registry);
};
$style = ImageStyle::load('mcp_apps_picker');
if ($style === NULL) {
  $style = ImageStyle::create(['name' => 'mcp_apps_picker', 'label' => 'MCP Apps picker thumbnail']);
  $style->addImageEffect([
    'id' => 'image_scale',
    'weight' => 0,
    'data' => ['width' => 640, 'height' => 640, 'upscale' => FALSE],
  ]);
  $style->save();
  $registry['config']['image.style.mcp_apps_picker'] = $style->uuid();
  $record();
}
$module = \Drupal::service('extension.list.module')->getPath('mcp_apps');
$manifest = json_decode(file_get_contents($module . '/assets/manifest.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load('image');
if ($media_type === NULL) {
  throw new RuntimeException('Enable an image Media type before seeding.');
}
$field_name = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();
$directory = 'public://mcp-apps-demo';
\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
foreach ($manifest as $photo) {
  $existing = FALSE;
  foreach ($registry['media'] as $id => $item) {
    $media = Media::load($id);
    if (($item['key'] ?? '') === $photo['key'] && $media !== NULL && $media->uuid() === $item['uuid']) {
      $existing = TRUE;
      break;
    }
  }
  if ($existing) {
    continue;
  }
  $file = \Drupal::service('file.repository')->writeData(file_get_contents($module . '/assets/' . $photo['file']), $directory . '/' . $photo['file'], FileExists::Rename);
  $registry['files'][$file->id()] = $file->uuid();
  $record();
  $media = Media::create([
    'bundle' => 'image',
    'name' => $photo['name'],
    'uid' => 1,
    'status' => 1,
    $field_name => ['target_id' => $file->id(), 'alt' => $photo['alt']],
  ]);
  $media->save();
  $registry['media'][$media->id()] = $photo + ['uuid' => $media->uuid()];
  $record();
}

require __DIR__ . '/seed_hero.php';
print json_encode(['media' => count($registry['media']), 'article_drafts' => count($registry['posts'])], JSON_PRETTY_PRINT) . PHP_EOL;
