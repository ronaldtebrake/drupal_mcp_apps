<?php

declare(strict_types=1);

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\mcp_server\Resource\CacheableResourceContent;
use Drupal\mcp_server\Resource\ResourceContentCache;
use Mcp\Schema\Extension\Apps\McpApps;

function audit_check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  print "PASS: $message\n";
}

foreach ([Drupal\mcp_server\Attribute\Tool::class, Drupal\mcp_server\Definition\ToolDefinition::class] as $class) {
  $parameters = array_map(static fn(ReflectionParameter $parameter): string => $parameter->getName(), (new ReflectionClass($class))->getConstructor()->getParameters());
  audit_check(!in_array('meta', $parameters, TRUE) && !in_array('_meta', $parameters, TRUE), "$class currently exposes no metadata constructor parameter");
}

$cache = new ResourceContentCache(Drupal::service('cache.default'), Drupal::service('cache_contexts_manager'));
foreach (['ui://audit/first', 'ui://audit/second'] as $uri) {
  $payload = [
    'uri' => $uri,
    'mimeType' => McpApps::MIME_TYPE,
    'text' => '<h1>Audit app</h1>',
    '_meta' => ['ui' => ['csp' => ['resourceDomains' => ['https://example.com']]]],
  ];
  $result = $cache->generate(
    $uri,
    static fn() => AccessResult::allowed(),
    static fn() => CacheableResourceContent::fromArray($payload, (new CacheableMetadata())->setCacheMaxAge(0)),
  );
  audit_check($result === $payload, "$uri survives Drupal resource content handling with MIME, HTML and CSP metadata intact");
}

$factory_source = file_get_contents(DRUPAL_ROOT . '/modules/contrib/mcp_server/src/McpServerFactory.php');
audit_check(!str_contains($factory_source, 'enableExtension('), 'Factory currently does not enable protocol extensions');
audit_check(method_exists(Mcp\Server\Builder::class, 'enableExtension'), 'Installed SDK provides enableExtension');
