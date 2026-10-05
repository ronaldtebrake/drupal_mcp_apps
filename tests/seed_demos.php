<?php

/**
 * @file
 * Seeds UUID-tracked sample Media, draft nodes and a real Drupal View.
 */

declare(strict_types=1);

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;

$state_key = 'mcp_apps.demo_data';
$registry = \Drupal::state()->get($state_key, [
  'media' => [],
  'files' => [],
  'nodes' => [],
  'config' => [],
  'users' => [],
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

$bundles = ['mcp_demo_article' => 'Articles', 'mcp_demo_event' => 'Events', 'mcp_demo_page' => 'Pages'];
foreach ($bundles as $id => $label) {
  $type = NodeType::load($id);
  if ($type === NULL) {
    $type = NodeType::create([
      'type' => $id,
      'name' => $label,
      'description' => 'MCP Apps sample draft content. Safe to remove with the demo cleanup script.',
    ]);
    $type->save();
    $registry['config']['node.type.' . $id] = $type->uuid();
    $record();
  }
  elseif (($registry['config']['node.type.' . $id] ?? NULL) !== $type->uuid()) {
    throw new RuntimeException('Refusing to seed into an untracked existing bundle: ' . $id);
  }
}
$editor = NULL;
foreach ($registry['users'] as $id => $uuid) {
  $candidate = User::load($id);
  if ($candidate !== NULL && $candidate->uuid() === $uuid) {
    $editor = $candidate;
  }
}
if ($editor === NULL) {
  $name = 'mcp_demo_editor';
  if (user_load_by_name($name)) {
    $name .= '_' . substr(\Drupal::service('uuid')->generate(), 0, 8);
  }
  $editor = User::create(['name' => $name, 'status' => 0, 'mail' => $name . '@example.invalid']);
  $editor->save();
  $registry['users'][$editor->id()] = $editor->uuid();
  $record();
}
$volumes = [
  'mcp_demo_article' => [2, 3, 4, 3, 5, 6, 4, 7, 6, 8, 7, 5],
  'mcp_demo_event' => [1, 0, 1, 2, 1, 2, 2, 1, 3, 2, 4, 3],
  'mcp_demo_page' => [1, 2, 1, 1, 2, 1, 2, 2, 1, 3, 2, 2],
];
$current = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('first day of this month')->setTime(0, 0);
$start = $current->modify('-11 months');
$seeded_keys = [];
foreach ($registry['nodes'] as $id => $item) {
  $node = Node::load($id);
  if ($node !== NULL && $node->uuid() === $item['uuid']) {
    $seeded_keys[$item['key']] = TRUE;
  }
}
foreach ($volumes as $bundle => $counts) {
  foreach ($counts as $month_index => $count) {
    $month = $start->modify('+' . $month_index . ' months');
    for ($number = 0; $number < $count; $number++) {
      $key = $bundle . ':' . $month->format('Y-m') . ':' . $number;
      if (isset($seeded_keys[$key])) {
        continue;
      }
      $node = Node::create([
        'type' => $bundle,
        'title' => '[MCP sample] ' . $bundles[$bundle] . ' · ' . $month->format('M Y') . ' · ' . ($number + 1),
        'status' => 0,
        'uid' => $number % 2 ? $editor->id() : 1,
        'created' => $month->modify('+' . min(3, $number) . ' days')->getTimestamp(),
      ]);
      $node->save();
      $registry['nodes'][$node->id()] = ['uuid' => $node->uuid(), 'key' => $key];
      $record();
    }
  }
}

$view = View::load('mcp_apps_content_activity');
if ($view !== NULL && ($registry['config']['views.view.mcp_apps_content_activity'] ?? NULL) !== $view->uuid()) {
  throw new RuntimeException('Refusing to overwrite an untracked View.');
}
if ($view === NULL) {
  $field = static fn(string $name): array => [
    'id' => $name,
    'table' => 'node_field_data',
    'field' => $name,
    'plugin_id' => 'field',
    'entity_type' => 'node',
    'entity_field' => $name,
    'label' => ucfirst($name),
  ];
  $view = View::create([
    'id' => 'mcp_apps_content_activity',
    'label' => 'MCP Apps: Content activity',
    'description' => 'Access-checked content rows for the MCP Apps chart, with an exposed author filter.',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'status' => TRUE,
    'display' => [
      'default' => [
        'id' => 'default',
        'display_title' => 'Content activity',
        'display_plugin' => 'default',
        'position' => 0,
        'display_options' => [
          'title' => 'Content activity',
          'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
          'cache' => ['type' => 'none'],
          'query' => ['type' => 'views_query', 'options' => ['disable_sql_rewrite' => FALSE]],
          'pager' => ['type' => 'some', 'options' => ['items_per_page' => 2000, 'offset' => 0]],
          'style' => ['type' => 'default'],
          'row' => ['type' => 'fields'],
          'fields' => [
            'nid' => $field('nid'),
            'created' => $field('created'),
            'type' => $field('type'),
            'uid' => $field('uid'),
          ],
          'sorts' => [
            'created' => [
              'id' => 'created',
              'table' => 'node_field_data',
              'field' => 'created',
              'plugin_id' => 'date',
              'order' => 'DESC',
            ],
          ],
          'filters' => [
            'uid' => [
              'id' => 'uid',
              'table' => 'node_field_data',
              'field' => 'uid',
              'plugin_id' => 'user_name',
              'operator' => 'in',
              'value' => [],
              'exposed' => TRUE,
              'expose' => [
                'identifier' => 'author',
                'label' => 'Author',
                'required' => FALSE,
                'use_operator' => FALSE,
              ],
            ],
          ],
        ],
      ],
    ],
  ]);
  $view->save();
  $registry['config']['views.view.mcp_apps_content_activity'] = $view->uuid();
  $record();
}
$displays = $view->get('display');
$displays['default']['display_options']['filters']['uid']['plugin_id'] = 'user_name';
$displays['default']['display_options']['filters']['uid']['operator'] = 'in';
$displays['default']['display_options']['filters']['uid']['value'] = [];
$view->set('display', $displays);
$view->save();
require __DIR__ . '/seed_hero.php';
print json_encode([
  'media' => count($registry['media']),
  'sample_drafts' => count($registry['nodes']),
  'view' => $view->id(),
  'demo_editor' => $editor->id(),
], JSON_PRETTY_PRINT) . PHP_EOL;
