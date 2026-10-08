<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_demo\Controller;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mcp_apps_activity_demo\ActivityData;
use Drupal\mcp_apps_activity_demo\Dashboard;
use Drupal\mcp_apps_activity_demo\DashboardComposition;
use Drupal\mcp_apps_activity_demo\Form\ActivityFiltersForm;
use Drupal\mcp_apps_openui\Composition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reuses the same Tool API report and SDCs on a normal Drupal page.
 */
final class ActivityController extends ControllerBase {

  public function __construct(
    private readonly Dashboard $dashboard,
    private readonly Composition $composition,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('mcp_apps_activity_demo.dashboard'), $container->get('mcp_apps_openui.composition'));
  }

  /**
   * Renders the actual components, not a copy of the app interface.
   */
  public function website(Request $request): array {
    $days = filter_var($request->query->get('days', 30), FILTER_VALIDATE_INT);
    $section = (string) $request->query->get('section', 'all');
    $view = (string) $request->query->get('view', 'dashboard');
    if ($days < 1 || $days > ActivityData::MAX_DAYS || !in_array($section, ['all', 'guides', 'culture'], TRUE)) {
      throw new BadRequestHttpException('Invalid activity filters.');
    }
    try {
      DashboardComposition::title($view);
    }
    catch (\InvalidArgumentException $e) {
      throw new BadRequestHttpException($e->getMessage());
    }
    $data = $this->dashboard->invoke('editorial_activity', ['days' => $days, 'section' => $section])['structuredContent']['data'];
    $tree = $this->composition->validate(DashboardComposition::tree($data, $view));
    $links = [];
    foreach ([7, 14, 30] as $period) {
      foreach (['all' => 'All sections', 'guides' => 'Guides', 'culture' => 'Culture'] as $key => $label) {
        $links[] = [
          '#type' => 'link',
          '#title' => $period . ' days · ' . $label,
          '#url' => Url::fromRoute('mcp_apps_activity_demo.website', [], [
            'query' => ['days' => $period, 'section' => $key, 'view' => $view],
          ]),
          '#attributes' => [
            'class' => ['pulse-filter'],
            'aria-current' => $days === $period && $section === $key ? 'page' : 'false',
          ],
        ];
      }
    }
    return [
      'custom' => $this->formBuilder()->getForm(ActivityFiltersForm::class, $days, $section, $view),
      'filters' => ['#type' => 'container', '#attributes' => ['class' => ['pulse-filters']], 'links' => $links],
      'dashboard' => $this->composition->renderArray($tree),
      '#cache' => ['max-age' => 0],
    ];
  }

}
