<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_demo;

use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Summarizes the bundled, fictional editorial dataset, never site analytics.
 */
final class ActivityData {

  // Reserve half the 60-day fixture for previous-period comparisons.
  public const MAX_DAYS = 30;

  public function __construct(private readonly ModuleExtensionList $modules) {}

  /**
   * Returns comparable current and previous periods for the selected section.
   */
  public function report(int $days, string $section): array {
    if ($days < 1 || $days > self::MAX_DAYS || !in_array($section, ['all', 'guides', 'culture'], TRUE)) {
      throw new \InvalidArgumentException('Choose between 1 and 30 days and an available section.');
    }
    $fixture = json_decode(file_get_contents(DRUPAL_ROOT . '/' . $this->modules->getPath('mcp_apps_activity_demo') . '/content/editorial.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $articles = array_values(array_filter($fixture['articles'], static fn($article) => $section === 'all' || $article['section'] === $section));
    $series = $previous = array_fill(0, $days, 0);
    $published = $review = 0;
    foreach ($articles as &$article) {
      $reads = array_slice($article['daily_reads'], -$days);
      foreach ($reads as $i => $value) {
        $series[$i] += $value;
      }
      foreach (array_slice($article['daily_reads'], -2 * $days, $days) as $i => $value) {
        $previous[$i] += $value;
      }
      $article['reads'] = array_sum($reads);
      $published += $article['status'] === 'Published' && $article['published_days_ago'] < $days ? 1 : 0;
      $review += $article['status'] === 'In review' ? 1 : 0;
      unset($article['daily_reads'], $article['published_days_ago']);
    }
    unset($article);
    usort($articles, static fn($a, $b) => $b['reads'] <=> $a['reads']);
    $labels = [];
    $end = new \DateTimeImmutable($fixture['as_of']);
    for ($i = $days - 1; $i >= 0; $i--) {
      $labels[] = $end->modify('-' . $i . ' days')->format('M j');
    }
    $reads = array_sum($series);
    $prior = array_sum($previous);
    return [
      'demo' => TRUE,
      'as_of' => $fixture['as_of'],
      'days' => $days,
      'section' => $section,
      'reads' => $reads,
      'change' => $prior ? round(($reads - $prior) / $prior * 100, 1) : 0,
      'published' => $published,
      'review' => $review,
      'engaged_minutes' => (int) round($reads * 2.7),
      'labels' => $labels,
      'series' => $series,
      'previous' => $previous,
      'articles' => $articles,
      'events' => array_values(array_filter($fixture['events'], static fn($event) => ($section === 'all' || $event['section'] === $section) && $event['days_ago'] < $days)),
    ];
  }

}
