<?php

/**
 * @file
 * Tests the hero workflow with a disposable copy of a seeded article.
 */

declare(strict_types=1);

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\mcp_apps\Mcp\HeroWorkflow;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Drupal\user\Entity\User;

$check = static function (bool $value, string $message): void {
  if (!$value) {
    throw new RuntimeException($message);
  }
  print 'PASS: ' . $message . PHP_EOL;
};
$account = \Drupal::currentUser()->getAccount();
$copy = NULL;
try {
  \Drupal::currentUser()->setAccount(User::load(1));
  $picker = new MediaPicker();
  $workflow = new HeroWorkflow();
  $data = $picker->open(article_title: 'weekend of discovery')->structuredContent;
  $check($data['node_id'] > 0, 'Article title resolves to one accessible article');
  $source = \Drupal::entityTypeManager()->getStorage('node')->load($data['node_id']);
  $copy = $source->createDuplicate();
  $copy->setTitle('Disposable hero workflow test');
  $copy->save();
  $post = array_values(array_filter($workflow->posts(), static fn(array $item): bool => $item['id'] === (int) $copy->id()))[0];
  $chosen = array_values(array_filter($data['media'], static fn(array $item): bool => $item['id'] !== $post['hero']['id']))[0];
  $old_revision = $copy->getRevisionId();
  $body = $copy->get('body')->getValue();
  $result = $workflow->save((int) $copy->id(), $chosen['id'], 'Article-specific hero description', $post['revision']);
  $check(!$result->isError, 'Confirmed hero saves successfully');
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $storage->resetCache([$copy->id()]);
  $saved = $storage->load($copy->id());
  $check(!$saved->isPublished() && $saved->getRevisionId() !== $old_revision, 'Save creates a new unpublished revision');
  $check($saved->get('body')->getValue() === $body && $saved->label() === $copy->label(), 'Article body and title remain unchanged');
  $check((int) $saved->get(HeroWorkflow::MEDIA_FIELD)->target_id === $chosen['id'], 'Hero references selected Media');
  $check($picker->open()->structuredContent['media'][array_search($chosen['id'], array_column($data['media'], 'id'), TRUE)]['alt'] === $chosen['alt'], 'Shared Media alt remains unchanged');
  $check($workflow->save((int) $copy->id(), $chosen['id'], 'Stale update', $post['revision'])->isError, 'Stale revision token rejects overwrite');
  $token = $result->structuredContent['post']['revision'];
  $check($workflow->save((int) $copy->id(), $chosen['id'], '', $token)->isError, 'Empty alt rejected');
  \Drupal::currentUser()->setAccount(new AnonymousUserSession());
  $check($workflow->save((int) $copy->id(), $chosen['id'], 'Unauthorised', $token)->isError, 'Anonymous update rejected');
  \Drupal::currentUser()->setAccount(User::load(1));
  $saved->setPublished();
  $saved->save();
  $check($workflow->save((int) $copy->id(), $chosen['id'], 'Published update', $token)->isError, 'Published article stays outside draft-only demo');
}
finally {
  \Drupal::currentUser()->setAccount(User::load(1));
  if ($copy !== NULL) {
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$copy->id()]);
    \Drupal::entityTypeManager()->getStorage('node')->load($copy->id())?->delete();
  }
  \Drupal::currentUser()->setAccount($account);
}
