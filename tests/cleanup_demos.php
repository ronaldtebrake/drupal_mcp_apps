<?php

/**
 * @file
 * Removes only UUID-tracked demo entities and unused configuration.
 */

declare(strict_types=1);

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\mcp_apps\Mcp\HeroWorkflow;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

$registry = \Drupal::state()->get('mcp_apps.demo_data', []);
foreach ($registry['posts'] ?? [] as $id => $item) {
  $node = Node::load($id);
  if ($node !== NULL && $node->uuid() === $item['uuid'] && $node->bundle() === HeroWorkflow::BUNDLE) {
    $node->delete();
  }
}
foreach ($registry['nodes'] ?? [] as $id => $item) {
  $node = Node::load($id);
  if ($node !== NULL && $node->uuid() === $item['uuid'] && str_starts_with($node->bundle(), 'mcp_demo_')) {
    $node->delete();
  }
}
foreach ($registry['media'] ?? [] as $id => $item) {
  $media = Media::load($id);
  if ($media !== NULL && $media->uuid() === $item['uuid']) {
    // Retain media that has since been attached to site content.
    $referenced = FALSE;
    foreach (\Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node') as $name => $definition) {
      if ($definition->getType() === 'entity_reference' && $definition->getSetting('target_type') === 'media') {
        if (\Drupal::entityQuery('node')->accessCheck(FALSE)->condition($name . '.target_id', $id)->range(0, 1)->execute()) {
          $referenced = TRUE;
          break;
        }
      }
    }
    if (!$referenced) {
      $media->delete();
    }
  }
}
foreach ($registry['files'] ?? [] as $id => $uuid) {
  $file = File::load($id);
  if ($file !== NULL && $file->uuid() === $uuid && !\Drupal::service('file.usage')->listUsage($file)) {
    $file->delete();
  }
}
$retained_bundles = [];
foreach ($registry['config'] ?? [] as $name => $uuid) {
  if (str_starts_with($name, 'node.type.')) {
    $bundle = substr($name, strlen('node.type.'));
    if (\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', $bundle)->count()->execute()) {
      $retained_bundles[$bundle] = TRUE;
    }
  }
}
foreach ($registry['config'] ?? [] as $name => $uuid) {
  $entity = \Drupal::service('config.manager')->loadConfigEntityByName($name);
  if ($entity === NULL || $entity->uuid() !== $uuid) {
    continue;
  }
  if (str_starts_with($name, 'node.type.') && isset($retained_bundles[$entity->id()])) {
    continue;
  }
  if (method_exists($entity, 'getTargetBundle') && isset($retained_bundles[$entity->getTargetBundle()])) {
    continue;
  }
  if ($entity->getEntityTypeId() === 'field_storage_config' && $entity->getBundles()) {
    continue;
  }
  $entity->delete();
}
foreach ($registry['users'] ?? [] as $id => $uuid) {
  $user = User::load($id);
  if ($user !== NULL && $user->uuid() === $uuid && !\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('uid', $id)->count()->execute()) {
    $user->delete();
  }
}
// Preserve tracking for protected data that can be cleaned up later.
foreach (['nodes' => 'node', 'posts' => 'node', 'media' => 'media', 'files' => 'file', 'users' => 'user'] as $key => $entity_type) {
  foreach ($registry[$key] ?? [] as $id => $item) {
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($id);
    $uuid = is_array($item) ? $item['uuid'] : $item;
    if ($entity === NULL || $entity->uuid() !== $uuid) {
      unset($registry[$key][$id]);
    }
  }
}
foreach ($registry['config'] ?? [] as $name => $uuid) {
  $entity = \Drupal::service('config.manager')->loadConfigEntityByName($name);
  if ($entity === NULL || $entity->uuid() !== $uuid) {
    unset($registry['config'][$name]);
  }
}
\Drupal::state()->set('mcp_apps.demo_data', $registry);
print "Removed tracked demo data; retained media referenced by nodes and configuration with additional content.\n";
