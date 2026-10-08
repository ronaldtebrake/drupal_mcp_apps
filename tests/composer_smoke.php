<?php

/**
 * @file
 * Exercises the read-only composer against a bootstrapped Drupal site.
 */

$account = \Drupal::entityTypeManager()->getStorage('user')->load(1);
\Drupal::service('account_switcher')->switchTo($account);
try {
  $composer = \Drupal::service('mcp_apps_openui.composer');
  $opened = $composer->open('A weekend in Rotterdam', getenv('MCP_APPS_PREVIEW_THEME') ?: '', '');
  $id = $opened['data']['session_id'];
  $preview = $composer->preview($id, $opened['ui']['tree']);
  $assets = \Drupal::service('mcp_apps_openui.sessions')->get($id)['assets'];
  $media = \Drupal::service('mcp_apps_demo.media')->search('Rotterdam');
  file_put_contents(getenv('MCP_APPS_UI_FIXTURE') ?: '/tmp/mcp-composer-fixture.json', json_encode([
    'boot' => $opened,
    'preview' => $preview,
    'media' => $media,
    'bytes' => array_map(static fn($asset) => base64_encode($asset['bytes']), $assets),
  ], JSON_THROW_ON_ERROR));
  print json_encode([
    'session' => $id,
    'nodes' => count($preview['ui']['tree']),
    'html_bytes' => strlen($preview['ui']['html']),
    'assets' => count($preview['ui']['assets']),
    'preview_theme' => $opened['ui']['theme'],
    'theme' => \Drupal::config('system.theme')->get('default'),
  ]) . PHP_EOL;
}
finally {
  \Drupal::service('account_switcher')->switchBack();
}
