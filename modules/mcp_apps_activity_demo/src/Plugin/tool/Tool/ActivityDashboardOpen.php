<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_demo\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps_activity_demo\ActivityData;
use Drupal\mcp_apps_activity_demo\Dashboard;
use Drupal\mcp_apps_openui\Plugin\tool\Tool\ComposerToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Opens the native Drupal activity dashboard in an MCP App.
 */
#[Tool(
  id: 'activity_dashboard_open',
  label: new TranslatableMarkup('Open editorial activity dashboard'),
  description: new TranslatableMarkup('Open a filterable editorial MCP App using native Drupal SDCs and OpenUI. Choose view dashboard for Editorial pulse, readership for Readership over time, stories for Stories making an impact, activity for the team timeline, or metrics for summary cards. Every view supports period and section filters through Tool API. Fictional demo data; no content is changed.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'view' => new InputDefinition(
      'string', 'View', 'dashboard, readership, stories, activity or metrics.', required: FALSE, default_value: 'dashboard',
      constraints: ['Choice' => ['choices' => ['dashboard', 'readership', 'stories', 'activity', 'metrics']]],
    ),
    'days' => new InputDefinition(
      'integer', 'Period', 'Number of days, from 1 to 30.', required: FALSE, default_value: 30,
      constraints: ['Range' => ['min' => 1, 'max' => ActivityData::MAX_DAYS]],
    ),
    'section' => new InputDefinition(
      'string', 'Section', 'all, guides or culture.', required: FALSE, default_value: 'all',
      constraints: ['Choice' => ['choices' => ['all', 'guides', 'culture']]],
    ),
  ],
  output_definitions: ['data' => new ContextDefinition('map', 'Activity report')],
  meta: ['mcp' => ['ui' => ['resourceUri' => Dashboard::URI]]],
)]
final class ActivityDashboardOpen extends ComposerToolBase {

  /**
   * The dashboard orchestrator.
   */
  private Dashboard $dashboard;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dashboard = $container->get('mcp_apps_activity_demo.dashboard');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->response($this->dashboard->open($values['days'], $values['section'], $values['view']), 'Filterable editorial view ready. All analytics are fictional demo data.');
  }

}
