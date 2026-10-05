<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_server\Attribute\Tool as McpTool;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\FormattedExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regression tests for the local upstream metadata contracts.
 *
 * @group mcp_apps
 */
#[Group('mcp_apps')]
final class NativeMetadataContractTest extends UnitTestCase {

  /**
   * Definition metadata survives authoring, derivatives and explicit clearing.
   */
  public function testDefinitionMetadata(): void {
    $meta = ['ui' => ['resourceUri' => 'ui://test/app'], 'test/custom' => ['value' => 7]];
    $label = new TranslatableMarkup('Test app');
    $mcp = new McpTool('test_app', $label, $label, meta: $meta);
    $mcp->setClass(self::class);
    $definition = $mcp->get();
    $this->assertSame($meta, $definition->meta);
    $this->assertSame($meta, $definition->withDerivative('first', $label)->meta);
    $this->assertSame([], $definition->withDerivative('second', $label, meta: [])->meta);
    $tool = new Tool('test_app', $label, $label, ToolOperation::Read, meta: $meta);
    $tool->setClass(self::class);
    $definition = $tool->get();
    $this->assertSame($meta, $definition->getMeta());
    $this->assertSame($meta, $definition->get('meta'));
    $this->assertSame([], $definition->setMeta([])->getMeta());
  }

  /**
   * Result formatting keeps presentation bytes separate from tool outputs.
   */
  public function testResultMetadata(): void {
    $values = ['message' => 'A useful textual fallback'];
    $meta = ['test/images' => ['hero' => 'data:image/png;base64,test']];
    $result = ExecutableResult::success(new TranslatableMarkup('Success'), $values, $meta);
    $formatted = new FormattedExecutableResult($result, $values, ['A hint']);
    $this->assertSame($meta, $result->getMeta());
    $this->assertSame($meta, $formatted->getMeta());
    $this->assertSame($values, $formatted->getContextValues());
    $this->assertSame([], ExecutableResult::success(new TranslatableMarkup('Plain tool'))->getMeta());
    $this->assertSame([], ExecutableResult::failure(new TranslatableMarkup('Failure'))->getMeta());
  }

}
