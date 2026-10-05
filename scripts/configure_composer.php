<?php

/**
 * @file
 * Merges the reproducible demo patches into a Drupal site's root Composer file.
 */

declare(strict_types=1);

$root = getcwd();
$module = dirname(__DIR__);
if (!is_file($root . '/composer.json') || !is_dir($root . '/web/core') || !str_starts_with($module, $root . '/')) {
  throw new RuntimeException('Run this script from the Drupal project root with the module installed below it.');
}
$fragment = json_decode(file_get_contents($module . '/patches/composer.root.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$composer = json_decode(file_get_contents($root . '/composer.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$composer['require'] = array_replace($composer['require'] ?? [], $fragment['require']);
$composer['config']['allow-plugins']['cweagans/composer-patches'] = TRUE;
foreach ($fragment['extra']['patches'] as $package => $patches) {
  $existing = $composer['extra']['patches'][$package] ?? [];
  // Accept both documented Composer Patches formats without losing patches.
  if (!array_is_list($existing)) {
    $existing = array_map(static fn(string $description, string $url): array => [
      'description' => $description,
      'url' => $url,
    ], array_keys($existing), $existing);
  }
  foreach ($patches as $patch) {
    $file = $module . '/patches/' . basename($patch['url']);
    $patch['url'] = substr($file, strlen($root) + 1);
    $patch['sha256'] = hash_file('sha256', $file);
    $existing = array_values(array_filter($existing, static fn(array $previous): bool => $previous['url'] !== $patch['url']));
    $existing[] = $patch;
  }
  $composer['extra']['patches'][$package] = $existing;
}
file_put_contents($root . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
print "Configured local MCP Apps patches. Run composer update, patches-relock and patches-repatch next.\n";
