<?php

/**
 * @file
 * Seeds UUID-tracked sample Media for the read-only component showcase.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
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
$module = \Drupal::service('extension.list.module')->getPath('mcp_apps_demo');
$manifest = json_decode(file_get_contents($module . '/assets/manifest.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load('image');
if ($media_type === NULL) {
  $media_type = MediaType::load('mcp_apps_image');
  if ($media_type !== NULL && ($registry['config']['media.type.mcp_apps_image'] ?? '') !== $media_type->uuid()) {
    throw new RuntimeException('Refusing to reuse an untracked demo image Media type.');
  }
  if ($media_type === NULL) {
    $media_type = MediaType::create(['id' => 'mcp_apps_image', 'label' => 'Demo image', 'source' => 'image']);
    $source = $media_type->getSource();
    $source_field = $source->createSourceField($media_type);
    $configuration = $source->getConfiguration();
    $configuration['source_field'] = $source_field->getName();
    $source->setConfiguration($configuration);
    $media_type->save();
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();
    $form = \Drupal::service('entity_display.repository')->getFormDisplay('media', $media_type->id(), 'default');
    $source->prepareFormDisplay($media_type, $form);
    $form->save();
    foreach ([$media_type, $source_field->getFieldStorageDefinition(), $source_field, $form] as $config) {
      $registry['config'][$config->getConfigDependencyName()] = $config->uuid();
    }
    $record();
  }
}
$registry['image_media_type'] = $media_type->id();
$record();
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
    'bundle' => $media_type->id(),
    'name' => $photo['name'],
    'uid' => 1,
    'status' => 1,
    $field_name => ['target_id' => $file->id(), 'alt' => $photo['alt']],
  ]);
  $media->save();
  $registry['media'][$media->id()] = $photo + ['uuid' => $media->uuid()];
  $record();
}

print json_encode(['media' => count($registry['media'])], JSON_PRETTY_PRINT) . PHP_EOL;
