<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_demo;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Url;
use Drupal\mcp_apps_openui\Composer;
use Drupal\mcp_apps_openui\ComposerSessions;

/**
 * Thin orchestration: reuse Tool API data and the native OpenUI preview.
 */
final class Dashboard {
  public const URI = 'ui://drupal/editorial-activity';

  public function __construct(
    private readonly PluginManagerInterface $tools,
    private readonly Composer $composer,
    private readonly ComposerSessions $sessions,
    private readonly ConfigFactoryInterface $config,
    private readonly ThemeExtensionList $themes,
  ) {}

  /**
   * Opens a read-only dashboard session.
   */
  public function open(int $days, string $section, string $view = 'dashboard'): array {
    $title = DashboardComposition::title($view);
    // Supply our own composition instead of triggering landing-page defaults.
    $opened = $this->composer->open($title, $this->config->get('system.theme')->get('default'), 'Page([])');
    $id = $opened['data']['session_id'];
    $state = $this->sessions->get($id);
    $state['activity_view'] = $view;
    $this->sessions->put($id, $state);
    return $this->filter($id, $days, $section);
  }

  /**
   * Resolves data through Tool API; rendering remains in native SDCs.
   */
  public function filter(string $session, int $days, string $section): array {
    // Validate ownership even before a data tool is called.
    $state = $this->sessions->get($session);
    $view = $state['activity_view'] ?? 'dashboard';
    $theme = $this->config->get('system.theme')->get('default');
    $filters = ['days' => $days, 'section' => $section];
    $data = $this->invoke('editorial_activity', $filters)['structuredContent']['data'];
    $state['theme'] = $theme;
    $this->sessions->put($session, $state);
    return [
      'data' => ['session_id' => $session, 'demo' => TRUE, 'reads' => $data['reads'], 'view' => $view] + $filters,
      'ui' => [
        'session_id' => $session,
        'tree' => DashboardComposition::tree($data, $view),
        'title' => DashboardComposition::title($view),
        'theme' => $theme,
        'theme_label' => $this->themes->getExtensionInfo($theme)['name'],
        'view' => $view,
        'as_of' => $data['as_of'],
        'days' => $days,
        'day_range' => ['min' => 1, 'max' => ActivityData::MAX_DAYS],
        'section' => $section,
        'website_url' => Url::fromRoute('mcp_apps_activity_demo.website', [], [
          'absolute' => TRUE,
          'query' => $filters + ['view' => $view],
        ])->toString(),
      ],
    ];
  }

  /**
   * Invokes only the read-only tools used by this demo.
   */
  public function invoke(string $name, array $inputs): array {
    if (!in_array($name, [
      'editorial_activity', 'activity_dashboard_open', 'activity_dashboard_filter',
      'component_composer_preview', 'component_composer_asset',
    ], TRUE)) {
      throw new \InvalidArgumentException('Unsupported activity operation.');
    }
    $tool = $this->tools->createInstance($name);
    if (array_diff(array_keys($inputs), array_keys($tool->getInputDefinitions()))) {
      throw new \InvalidArgumentException('Unknown activity inputs.');
    }
    foreach ($inputs as $key => $value) {
      $tool->setInputValue($key, $value);
    }
    if (!$tool->access()) {
      throw new \InvalidArgumentException('Access denied or invalid activity inputs.');
    }
    if ($tool->validateInputs()->count()) {
      throw new \InvalidArgumentException('Invalid activity inputs.');
    }
    $result = $tool->execute()->getResult();
    if (!$result->isSuccess()) {
      throw new \InvalidArgumentException((string) $result->getMessage());
    }
    return [
      'structuredContent' => $result->getContextValues(),
      '_meta' => $result->getMeta()['mcp'] ?? [],
      'content' => [['type' => 'text', 'text' => (string) $result->getMessage()]],
    ];
  }

}
