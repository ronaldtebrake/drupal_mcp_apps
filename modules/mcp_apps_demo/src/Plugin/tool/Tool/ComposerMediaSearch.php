<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_demo\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_apps_demo\MediaLibrary;
use Drupal\mcp_apps_openui\Composer;
use Drupal\mcp_apps_openui\Plugin\tool\Tool\ComposerToolBase;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides access-checked Media choices and app-only thumbnail bytes.
 */
#[Tool(
  id: 'composer_media_search',
  label: new TranslatableMarkup('Find composer images'),
  description: new TranslatableMarkup('Find accessible public Drupal image Media for a component composition. Image bytes are app-only presentation metadata.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'query' => new InputDefinition('string', 'Search', 'Image name.', required: FALSE, default_value: '', constraints: ['Length' => ['max' => 200]]),
  ],
  output_definitions: ['data' => new ContextDefinition('map', 'Media')],
  meta: ['mcp' => ['ui' => ['resourceUri' => Composer::URI, 'visibility' => ['app', 'model']]]],
)]
final class ComposerMediaSearch extends ComposerToolBase {

  /**
   * The access-checked image library.
   */
  private MediaLibrary $mediaLibrary;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->mediaLibrary = $container->get('mcp_apps_demo.media');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    return $this->response($this->mediaLibrary->search($values['query']), 'Accessible composer images.');
  }

}
