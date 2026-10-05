<?php

/**
 * @file
 * Adds two small article drafts for the Olivero hero-image workflow.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\mcp_apps\Mcp\HeroWorkflow;
use Drupal\mcp_apps\HeroArticle;
use Drupal\image\Entity\ImageStyle;
use Drupal\Component\Serialization\Yaml;

$registry = \Drupal::state()->get('mcp_apps.demo_data', []);
$registry['posts'] ??= [];
$record = static function () use (&$registry): void {
  \Drupal::state()->set('mcp_apps.demo_data', $registry);
};
if (ImageStyle::load(HeroArticle::IMAGE_STYLE) === NULL) {
  $path = \Drupal::service('extension.list.module')->getPath('mcp_apps');
  $config = Yaml::decode(file_get_contents($path . '/config/install/image.style.mcp_apps_hero.yml'));
  $style = ImageStyle::create($config);
  $style->save();
  $registry['config']['image.style.' . HeroArticle::IMAGE_STYLE] = $style->uuid();
  $record();
}
$bundle = HeroWorkflow::BUNDLE;
$type = NodeType::load($bundle);
if ($type === NULL) {
  $type = NodeType::create([
    'type' => $bundle,
    'name' => 'Demo article',
    'description' => 'Article drafts for the MCP Apps hero-image workflow.',
    'new_revision' => TRUE,
  ]);
  $type->save();
  $registry['config']['node.type.' . $bundle] = $type->uuid();
  $record();
}
elseif (($registry['config']['node.type.' . $bundle] ?? '') !== $type->uuid()) {
  throw new RuntimeException('Refusing to modify an untracked article type.');
}
foreach ([
  HeroWorkflow::MEDIA_FIELD => [
    'entity_reference',
    ['target_type' => 'media'],
    'Hero image',
    [
      'handler' => 'default:media',
      'handler_settings' => ['target_bundles' => ['image' => 'image']],
    ],
  ],
  HeroWorkflow::ALT_FIELD => ['string_long', [], 'Hero alt text', []],
  'body' => ['text_with_summary', [], 'Body', []],
] as $name => [$field_type, $storage_settings, $label, $settings]) {
  $storage = FieldStorageConfig::loadByName('node', $name);
  if ($storage === NULL) {
    $storage = FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $name,
      'type' => $field_type,
      'settings' => $storage_settings,
    ]);
    $storage->save();
    $registry['config']['field.storage.node.' . $name] = $storage->uuid();
    $record();
  }
  $field = FieldConfig::loadByName('node', $bundle, $name);
  if ($field === NULL) {
    $field = FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => $bundle,
      'field_name' => $name,
      'label' => $label,
      'settings' => $settings,
    ]);
    $field->save();
    $registry['config']['field.field.node.' . $bundle . '.' . $name] = $field->uuid();
    $record();
  }
}
$display_id = 'node.' . $bundle . '.default';
if (EntityViewDisplay::load($display_id) === NULL) {
  $display = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => $bundle,
    'mode' => 'default',
    'status' => TRUE,
  ]);
  $display->setComponent(HeroWorkflow::MEDIA_FIELD, [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'weight' => 0,
    'settings' => ['view_mode' => 'default', 'link' => FALSE],
  ]);
  $display->setComponent('body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 1]);
  $display->removeComponent(HeroWorkflow::ALT_FIELD);
  $display->save();
  $registry['config']['core.entity_view_display.' . $display_id] = $display->uuid();
  $record();
}
$samples = [
  [
    'rotterdam',
    'A weekend of discovery in Rotterdam',
    'From riverside walks to remarkable architecture, Rotterdam is a city best explored with curiosity. Here are a few places to start your next visit.',
    'rotterdam-aerial',
  ],
  [
    'community',
    'Good ideas start with a conversation',
    'When people share what they know, new possibilities follow. Discover how a community gathering can turn small conversations into lasting connections.',
    'conference-audience',
  ],
];
foreach ($samples as [$key, $title, $summary, $photo_key]) {
  foreach ($registry['posts'] as $id => $item) {
    if ($item['key'] === $key && ($existing = Node::load($id)) && $existing->uuid() === $item['uuid']) {
      continue 2;
    }
  }
  $media_id = NULL;
  foreach ($registry['media'] ?? [] as $id => $photo) {
    if ($photo['key'] === $photo_key) {
      $media_id = $id;
      break;
    }
  }
  $media_id ??= array_key_first($registry['media']);
  $media = \Drupal::entityTypeManager()->getStorage('media')->load($media_id);
  $media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media->bundle());
  $image_field = $media->getSource()->getSourceFieldDefinition($media_type)->getName();
  $node = Node::create([
    'type' => $bundle,
    'title' => $title,
    'status' => 0,
    'uid' => 1,
    'body' => ['value' => $summary, 'summary' => $summary, 'format' => 'plain_text'],
    HeroWorkflow::MEDIA_FIELD => ['target_id' => $media_id],
    HeroWorkflow::ALT_FIELD => $media->get($image_field)->alt,
  ]);
  $node->save();
  $registry['posts'][$node->id()] = ['uuid' => $node->uuid(), 'key' => $key];
  $record();
}
print json_encode(['article_drafts' => array_keys($registry['posts'])], JSON_PRETTY_PRINT) . PHP_EOL;
