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
  $check((new MediaPicker())->open()->isError, 'Anonymous picker is denied without the module permission');
  $check((new MediaPicker())->open()->structuredContent === NULL, 'Denied picker exposes no structured content');
}
finally {
  \Drupal::currentUser()->setAccount($account);
}
