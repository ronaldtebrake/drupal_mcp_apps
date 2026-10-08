<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_demo;

/**
 * One composition shared by the website and the OpenUI MCP App.
 */
final class DashboardComposition {

  /**
   * Names the supported dashboard and focused component views.
   */
  public static function title(string $view): string {
    return match ($view) {
      'dashboard' => 'Editorial pulse',
      'readership' => 'Readership over time',
      'stories' => 'Stories making an impact',
      'activity' => 'Behind the stories',
      'metrics' => 'Editorial metrics',
      default => throw new \InvalidArgumentException('Select an available activity view.'),
    };
  }

  /**
   * Maps a reusable Tool API report to ordinary SDC props and slots.
   */
  public static function tree(array $data, string $view = 'dashboard'): array {
    self::title($view);
    $node = static fn($id, $props, $slots = []) => [
      'component' => 'mcp_apps_activity_demo:' . $id,
      'props' => $props,
      'slots' => $slots,
    ];
    $change = ($data['change'] >= 0 ? '+' : '') . $data['change'] . '%';
    $dashboard = $node('dashboard', [
      'title' => 'Editorial pulse',
      'description' => 'Good stories deserve an audience. See what is resonating, and what your team is working on next.',
      'period' => $data['days'] . ' days · ' . ($data['section'] === 'all' ? 'All sections' : ucfirst($data['section'])),
      'as_of' => $data['as_of'],
    ], [
      'metrics' => [
        $node('metric', [
          'label' => 'Article reads',
          'value' => number_format($data['reads']),
          'note' => $change . ' vs previous period',
          'trend' => $data['series'],
          'tone' => 'mint',
        ]),
        $node('metric', [
          'label' => 'Engaged minutes',
          'value' => number_format($data['engaged_minutes']),
          'note' => 'Estimated · 2.7 min per read',
          'trend' => [],
          'tone' => 'violet',
        ]),
        $node('metric', [
          'label' => 'New stories',
          'value' => (string) $data['published'],
          'note' => 'Published in this period',
          'trend' => [],
          'tone' => 'blue',
        ]),
        $node('metric', [
          'label' => 'In the pipeline',
          'value' => (string) $data['review'],
          'note' => 'Stories ready for review',
          'trend' => [],
          'tone' => 'amber',
        ]),
      ],
      'chart' => [$node('readership', [
        'labels' => $data['labels'],
        'current' => $data['series'],
        'previous' => $data['previous'],
      ]),
      ],
      'content' => [$node('stories', ['articles' => $data['articles']])],
      'activity' => [$node('activity', ['events' => $data['events']])],
    ]);
    if ($view === 'dashboard') {
      return [$dashboard];
    }
    $slot = ['readership' => 'chart', 'stories' => 'content', 'activity' => 'activity', 'metrics' => 'metrics'][$view];
    return $dashboard['slots'][$slot];
  }

}
