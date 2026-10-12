<?php

/**
 * @file
 * Generates a generic native SDC fixture using the optional Commerce demo.
 */

\Drupal::service('account_switcher')->switchTo(\Drupal\user\Entity\User::load(1));
try {
  $opened = \Drupal::service('mcp_apps_commerce_demo.shop')->open('', 'grinders', 150);
  $id = $opened['data']['session_id'];
  $opened['ui']['catalog'] = \Drupal::service('mcp_apps_openui.catalog')->list('mcp_apps_commerce_demo');
  $opened['ui']['initial_component'] = 'mcp_apps_commerce_demo:shop';
  $preview = \Drupal::service('mcp_apps_openui.composer')->preview($id, $opened['ui']['tree']);
  $assets = \Drupal::service('mcp_apps_openui.sessions')->get($id)['assets'];
  file_put_contents(getenv('MCP_APPS_UI_FIXTURE') ?: '/tmp/mcp-composer-fixture.json', json_encode([
    'boot' => $opened, 'preview' => $preview,
    'bytes' => array_map(static fn($asset) => base64_encode($asset['bytes']), $assets),
  ], JSON_THROW_ON_ERROR));
  print json_encode(['components' => count($opened['ui']['catalog']), 'assets' => count($assets)]) . PHP_EOL;
}
finally {
  \Drupal::service('account_switcher')->switchBack();
}
