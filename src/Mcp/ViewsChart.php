<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Mcp;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\node\NodeInterface;
use Drupal\views\Views;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;

/**
 * Presents the rows of one real Drupal View as an interactive chart.
 */
final class ViewsChart {

  public const URI = 'ui://drupal/views-chart';

  /**
   * Executes the configured View with an exposed author filter.
   */
  #[McpTool(
    name: 'views_chart_open',
    title: 'Drupal Views: Content Pulse',
    description: 'Open an interactive content creation chart backed by the mcp_apps_content_activity Drupal View. Switch line/bar charts and toggle content types locally. Refresh or change author through a server call that reruns the View exposed filter. Default scope is labelled sample draft content; scope all includes accessible real site content. Never changes content.',
    annotations: new ToolAnnotations(readOnlyHint: TRUE, openWorldHint: FALSE),
    meta: ['ui' => ['resourceUri' => self::URI]],
  )]
  public function open(int $months = 12, string $scope = 'demo', int $author = 0): CallToolResult {
    return DemoSupport::respond(function () use ($months, $scope, $author): array {
      if (!in_array($months, [3, 6, 12], TRUE) || !in_array($scope, ['demo', 'all'], TRUE) || $author < 0) {
        throw new \InvalidArgumentException('Choose months 3, 6 or 12; scope demo or all; and a non-negative author ID (0 means all authors).');
      }
      $view = Views::getView('mcp_apps_content_activity');
      if ($view === NULL || !$view->access('default')) {
        throw new \InvalidArgumentException('The content activity View is unavailable to this account.');
      }
      $view->setDisplay('default');
      $input = [];
      if ($author > 0) {
        $account = \Drupal::entityTypeManager()->getStorage('user')->load($author);
        if ($account === NULL || !$account->access('view') || !$account->get('name')->access('view')) {
          throw new \InvalidArgumentException('That author is unavailable to this account.');
        }
        // The native Views uid handler uses Drupal's entity autocomplete.
        $input['author'] = EntityAutocomplete::getEntityLabels([$account]);
      }
      $view->setExposedInput($input);
      $view->execute();
      $current = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('first day of this month')->setTime(0, 0);
      $start = $current->modify('-' . ($months - 1) . ' months');
      $end = $current->modify('+1 month')->getTimestamp();
      $keys = [];
      $labels = [];
      for ($i = 0; $i < $months; $i++) {
        $month = $start->modify('+' . $i . ' months');
        $keys[] = $month->format('Y-m');
        $labels[] = $month->format('M Y');
      }
      $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
      $series = [];
      $authors = [];
      foreach ($view->result as $row) {
        $node = $row->_entity ?? NULL;
        if (!$node instanceof NodeInterface || !$node->access('view') || !$node->get('created')->access('view') || !$node->get('type')->access('view') || !$node->get('uid')->access('view')) {
          continue;
        }
        if ($scope === 'demo' && !str_starts_with($node->bundle(), 'mcp_demo_')) {
          continue;
        }
        $created = (int) $node->getCreatedTime();
        if ($created < $start->getTimestamp() || $created >= $end || ($author > 0 && (int) $node->getOwnerId() !== $author)) {
          continue;
        }
        $bundle = $node->bundle();
        $series[$bundle] ??= [
          'id' => $bundle,
          'name' => $bundle_info[$bundle]['label'] ?? $bundle,
          'values' => array_fill(0, $months, 0),
        ];
        $index = array_search(gmdate('Y-m', $created), $keys, TRUE);
        if ($index !== FALSE) {
          $series[$bundle]['values'][$index]++;
        }
        $owner = $node->getOwner();
        if ($owner !== NULL && $owner->access('view') && $owner->get('name')->access('view')) {
          $authors[(int) $owner->id()] = ['id' => (int) $owner->id(), 'name' => $owner->getDisplayName()];
        }
      }
      if ($author > 0) {
        $authors += $this->authorChoices($scope);
      }
      $total = array_sum(array_map(static fn(array $item): int => array_sum($item['values']), $series));
      $limited = count($view->result) >= 2000;
      $view->destroy();
      return [
        'app' => 'views-chart',
        'origin' => DemoSupport::origin(),
        'view' => ['id' => 'mcp_apps_content_activity', 'display' => 'default'],
        'labels' => $labels,
        'series' => array_values($series),
        'authors' => array_values($authors),
        'filters' => ['months' => $months, 'scope' => $scope, 'author' => $author],
        'limited' => $limited,
        'timezone' => 'UTC',
        'message' => sprintf('%d accessible content records across %d months from Drupal Views%s%s.', $total, $months, $scope === 'demo' ? ' (sample drafts)' : '', $limited ? '; bounded to 2,000 View rows' : ''),
      ];
    });
  }

  /**
   * Gets accessible author choices without bypassing node access.
   */
  private function authorChoices(string $scope): array {
    $query = \Drupal::entityQuery('node')->accessCheck(TRUE)->range(0, 2000);
    if ($scope === 'demo') {
      $query->condition('type', ['mcp_demo_article', 'mcp_demo_event', 'mcp_demo_page'], 'IN');
    }
    $authors = [];
    foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($query->execute()) as $node) {
      if (!$node->access('view') || !$node->get('uid')->access('view')) {
        continue;
      }
      $owner = $node->getOwner();
      if ($owner !== NULL && $owner->access('view') && $owner->get('name')->access('view')) {
        $authors[(int) $owner->id()] = ['id' => (int) $owner->id(), 'name' => $owner->getDisplayName()];
      }
    }
    return $authors;
  }

  /**
   * Returns the bundled official-SDK app.
   */
  #[McpResource(uri: self::URI, name: 'drupal-views-chart', title: 'Drupal Views: Content Pulse', mimeType: McpApps::MIME_TYPE, meta: ['ui' => new \stdClass()])]
  public function resource(): TextResourceContents {
    return DemoSupport::resource('views-chart');
  }

}
