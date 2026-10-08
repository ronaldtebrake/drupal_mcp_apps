<?php

/**
 * @file
 * Hooks provided by the MCP Apps OpenUI integration.
 */

/**
 * Alters the initial, account-bound component preview state.
 *
 * @param array $data
 *   State with title, installed theme, OpenUI program, and component tree.
 *   Supply a tree only when program is empty. Nodes contain component, props,
 *   and slots; Drupal validates them before rendering. Optional media_query
 *   and initial_component values control the initial inspector selection.
 */
function hook_mcp_apps_composer_defaults_alter(array &$data): void {
  if ($data['program'] === '' && $data['theme'] === 'my_theme') {
    $data['tree'] = [
      [
        'component' => 'my_theme:heading',
        'props' => ['text' => 'Hello from Drupal'],
        'slots' => [],
      ],
    ];
  }
}
