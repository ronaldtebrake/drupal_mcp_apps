<?php

/**
 * @file
 * Creates the explicit demo role and OAuth scope used by the sample workflow.
 */

declare(strict_types=1);

use Drupal\mcp_server_tool_bridge\Entity\McpToolConfig;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

$registry = \Drupal::state()->get('mcp_apps.demo_data', []);
$role_id = 'mcp_apps_demo_editor';
$scope_id = 'mcp_apps_demo';
$role = Role::load($role_id);
if ($role !== NULL && ($registry['oauth']['role'] ?? '') !== $role->uuid()) {
  throw new RuntimeException('Refusing to modify an untracked demo editor role.');
}
if ($role === NULL) {
  $role = Role::create([
    'id' => $role_id,
    'label' => 'MCP Apps demo editor',
    'permissions' => [
      'access mcp server',
      'access mcp media picker',
      'update mcp article hero',
      'access content',
      'view media',
      'bypass node access',
      'grant simple_oauth codes',
    ],
    'dependencies' => ['enforced' => ['module' => ['mcp_apps']]],
  ]);
  $role->save();
  $registry['oauth']['role'] = $role->uuid();
  \Drupal::state()->set('mcp_apps.demo_data', $registry);
}
$scope = Oauth2Scope::load($scope_id);
if ($scope !== NULL && ($registry['oauth']['scope'] ?? '') !== $scope->uuid()) {
  throw new RuntimeException('Refusing to modify an untracked demo OAuth scope.');
}
if ($scope === NULL) {
  $scope = Oauth2Scope::create([
    'name' => $scope_id,
    'description' => 'Use the MCP Apps Media picker and update demo draft heroes.',
    'grant_types' => [
      'authorization_code' => ['status' => TRUE, 'description' => 'Use the MCP Apps demo'],
      'refresh_token' => ['status' => TRUE, 'description' => 'Refresh MCP Apps demo access'],
    ],
    'granularity_id' => Oauth2ScopeInterface::GRANULARITY_ROLE,
    'granularity_configuration' => ['role' => $role_id],
    'dependencies' => ['enforced' => ['module' => ['mcp_apps']]],
  ]);
  $scope->save();
  $registry['oauth']['scope'] = $scope->uuid();
  \Drupal::state()->set('mcp_apps.demo_data', $registry);
}
$administrator = User::load(1);
if ($administrator !== NULL && !$administrator->hasRole($role_id)) {
  $administrator->addRole($role_id)->save();
}
// Advertise the scope for discovery; Drupal still checks access.
foreach (['media_picker_open', 'media_picker_save_hero'] as $id) {
  $tool = McpToolConfig::load($id);
  if ($tool !== NULL && !$tool->getThirdPartySetting('mcp_server_oauth', 'scopes')) {
    $tool->setThirdPartySetting('mcp_server_oauth', 'scopes', [$scope_id])->save();
  }
}
