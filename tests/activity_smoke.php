<?php

/**
 * @file
 * Generates native Drupal fixtures for the activity MCP App UI regression.
 */

$account = \Drupal::entityTypeManager()->getStorage('user')->load(1);
\Drupal::service('account_switcher')->switchTo($account);
try {
  $dashboard = \Drupal::service('mcp_apps_activity_demo.dashboard');
  $opened = $dashboard->invoke('activity_dashboard_open', ['view' => getenv('MCP_APPS_ACTIVITY_VIEW') ?: 'dashboard']);
  $id = $opened['structuredContent']['data']['session_id'];
  $composer = \Drupal::service('mcp_apps_openui.composer');
  $preview = $composer->preview($id, $opened['_meta']['ui']['tree']);
  $assets = \Drupal::service('mcp_apps_openui.sessions')->get($id)['assets'];
  $filtered = $dashboard->invoke('activity_dashboard_filter', ['session_id' => $id, 'days' => 7, 'section' => 'culture']);
  $filteredPreview = $composer->preview($id, $filtered['_meta']['ui']['tree']);
  $filteredAssets = \Drupal::service('mcp_apps_openui.sessions')->get($id)['assets'];
  $custom = $dashboard->invoke('activity_dashboard_filter', ['session_id' => $id, 'days' => 13, 'section' => 'culture']);
  $customPreview = $composer->preview($id, $custom['_meta']['ui']['tree']);
  $customAssets = \Drupal::service('mcp_apps_openui.sessions')->get($id)['assets'];
  $switched = $switchedPreview = NULL;
  $switchedAssets = [];
  if ($theme = getenv('MCP_APPS_ACTIVITY_ALTERNATE_THEME')) {
    $sessions = \Drupal::service('mcp_apps_openui.sessions');
    $state = $sessions->get($id);
    $state['theme'] = $theme;
    $sessions->put($id, $state);
    $switched = $filtered;
    $switched['_meta']['ui']['theme'] = $theme;
    $switched['_meta']['ui']['theme_label'] = \Drupal::service('extension.list.theme')->getExtensionInfo($theme)['name'];
    $switchedPreview = $composer->preview($id, $filtered['_meta']['ui']['tree']);
    $switchedAssets = $sessions->get($id)['assets'];
  }
  file_put_contents(getenv('MCP_APPS_ACTIVITY_FIXTURE') ?: '/tmp/mcp-activity-fixture.json', json_encode([
    'opened' => $opened,
    'preview' => $preview,
    'filtered' => $filtered,
    'filtered_preview' => $filteredPreview,
    'custom' => $custom,
    'custom_preview' => $customPreview,
    'switched' => $switched,
    'switched_preview' => $switchedPreview,
    'bytes' => array_map(static fn($asset) => base64_encode($asset['bytes']), $assets + $filteredAssets + $customAssets + $switchedAssets),
  ], JSON_THROW_ON_ERROR));
  print json_encode([
    'reads' => $opened['structuredContent']['data']['reads'],
    'assets' => count($assets),
    'filtered_reads' => $filtered['structuredContent']['data']['reads'],
  ]) . PHP_EOL;
}
finally {
  \Drupal::service('account_switcher')->switchBack();
}
