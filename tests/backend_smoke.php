<?php

/**
 * @file
 * Checks entity access and server-filter correctness against real demo data.
 */

declare(strict_types=1);

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\mcp_apps\Mcp\MediaPicker;
use Drupal\mcp_apps\Mcp\ViewsChart;
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
  $data = (new ViewsChart())->open()->structuredContent;
  $count = static function (array $result): int {
    $total = 0;
    foreach ($result['series'] as $series) {
      $total += array_sum($series['values']);
    }
    return $total;
  };
  $check($count($data) === count(\Drupal::state()->get('mcp_apps.demo_data')['nodes']), 'View chart counts all tracked sample drafts');
  $admin = (new ViewsChart())->open(author: 1)->structuredContent;
  $editor_id = (int) array_key_first(\Drupal::state()->get('mcp_apps.demo_data')['users']);
  $editor = (new ViewsChart())->open(author: $editor_id)->structuredContent;
  $check($count($admin) > 0 && $count($editor) > 0 && $count($admin) + $count($editor) === $count($data), 'View exposed author filters partition the actual node rows');
  $check((new ViewsChart())->open(months: 99)->isError, 'Invalid chart period rejected');
  $check((new ViewsChart())->open(scope: 'arbitrary')->isError, 'Invalid scope rejected');
  $check((new MediaPicker())->open(str_repeat('a', 201))->isError, 'Oversized media search rejected');
  $check(count((new MediaPicker())->open('nonexistent-sentinel')->structuredContent['media']) === 0, 'Empty media search returns no records');
  \Drupal::currentUser()->setAccount(new AnonymousUserSession());
  $anonymous = (new ViewsChart())->open();
  $check($anonymous->isError || $count($anonymous->structuredContent) === 0, 'Anonymous chart cannot expose unpublished sample nodes');
  $storage = \Drupal::entityTypeManager()->getStorage('media');
  $sample_id = (int) array_key_first(\Drupal::state()->get('mcp_apps.demo_data')['media']);
  $sample = $storage->load($sample_id);
  \Drupal::currentUser()->setAccount(User::load(1));
  $published = $sample->isPublished();
  try {
    $sample->setUnpublished();
    $sample->save();
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
