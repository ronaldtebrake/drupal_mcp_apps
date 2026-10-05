<?php

/**
 * @file
 * Checks Media search and entity access against real demo data.
 */

declare(strict_types=1);

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Drupal\user\Entity\User;

$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  print 'PASS: ' . $message . PHP_EOL;
};
$account = \Drupal::currentUser()->getAccount();
try {
  \Drupal::currentUser()->setAccount(User::load(1));
  $check((new MediaPicker())->open(str_repeat('a', 201))->isError, 'Oversized media search rejected');
  $check(count((new MediaPicker())->open('nonexistent-sentinel')->structuredContent['media']) === 0, 'Empty media search returns no records');
  \Drupal::currentUser()->setAccount(new AnonymousUserSession());
  $check((new MediaPicker())->open()->structuredContent['posts'] === [], 'Anonymous picker cannot expose unpublished article drafts');
  $storage = \Drupal::entityTypeManager()->getStorage('media');
  $sample_id = (int) array_key_first(\Drupal::state()->get('mcp_apps.demo_data')['media']);
  $sample = $storage->load($sample_id);
  \Drupal::currentUser()->setAccount(User::load(1));
  $published = $sample->isPublished();
  try {
    $sample->setUnpublished();
    $sample->save();
    // An earlier anonymous read cached access within this PHP process.
    \Drupal::entityTypeManager()->getAccessControlHandler('media')->resetCache();
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
    $visible_ids = array_column((new MediaPicker())->open()->structuredContent['media'], 'id');
    $check(!in_array($sample_id, $visible_ids, TRUE), 'Anonymous media picker cannot expose unpublished Media');
  }
  finally {
    \Drupal::currentUser()->setAccount(User::load(1));
    $sample->set('status', $published);
    $sample->save();
  }
}
finally {
  \Drupal::currentUser()->setAccount($account);
}
