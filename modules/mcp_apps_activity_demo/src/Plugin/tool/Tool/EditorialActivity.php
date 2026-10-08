<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_demo\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps_activity_demo\ActivityData;
use Drupal\mcp_apps_openui\Plugin\tool\Tool\ComposerToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns a reusable report from the fictional editorial dataset.
 */
#[Tool(
  id: 'editorial_activity',
  label: new TranslatableMarkup('Read editorial activity'),
  description: new TranslatableMarkup('Return fictional editorial readership, story performance and activity for any period from 1 to 30 days. Demo data, not site analytics. Reusable independently of the dashboard.'),
  operation: ToolOperation::Read,
  input_definitions: [
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

)]
final class EditorialActivity extends ComposerToolBase {

  /**
   * The fictional dataset.
   */
  private ActivityData $data;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->data = $container->get('mcp_apps_activity_demo.data');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return ExecutableResult::success(new TranslatableMarkup('Fictional editorial activity report.'), ['data' => $this->data->report($values['days'], $values['section'])]);
  }

}
