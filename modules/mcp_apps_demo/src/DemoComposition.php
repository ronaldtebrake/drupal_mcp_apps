<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_demo;

/**
 * Sample data only; markup and styling come from the selected theme's SDCs.
 */
final class DemoComposition {

  /**
   * Builds equivalent compositions using Mercury or Byte components.
   */
  public static function tree(string $title, string $provider = 'mcp_apps_demo_theme'): array {
    if (!in_array($provider, ['mcp_apps_demo_theme', 'byte_theme'], TRUE)) {
      throw new \InvalidArgumentException('Select a supported demo component provider.');
    }
    $byte = $provider === 'byte_theme';
    $items = \Drupal::service('mcp_apps_demo.media')->search('Rotterdam')['data']['media'];
    if (count($items) < 4) {
      throw new \InvalidArgumentException('Run the explicit demo seed before opening the Rotterdam composition.');
    }
    $node = static fn(string $name, array $props, array $slots = []) => [
      'component' => $provider . ':' . $name,
      'props' => $props,
      'slots' => $slots,
    ];
    $heading = static fn(string $text, int $level = 2) => $node('heading', [
      'heading_text' => $text,
      'level' => $level,
      'text_size' => $level === 1 ? 'heading-responsive-6xl' : 'heading-responsive-3xl',
      'text_color' => 'default',
      'align' => 'left',
    ]);
    $section = [
      'columns' => '100',
      'mobile_columns' => '1',
      'width' => '90%',
      'margin_block_start' => '0',
      'margin_block_end' => '0',
      'padding_block_start' => '32',
      'padding_block_end' => '32',
    ];
    $cards = [
      $node('card', [
        'heading_text' => 'Walk the waterfront',
        'text' => 'Meet the Erasmus Bridge from a new perspective.',
        'orientation' => 'vertical',
        'style' => 'framed',
        'media' => ['media_id' => $items[1]['id']],
        'level' => 3,
      ]),
      $node('card', [
        'heading_text' => 'Follow your curiosity',
        'text' => 'Architecture, hidden corners, and plenty of room to explore.',
        'orientation' => 'vertical',
        'style' => 'framed',
        'media' => ['media_id' => $items[2]['id']],
        'level' => 3,
      ]),
      $node('card', [
        'heading_text' => 'Stay for golden hour',
        'text' => 'Slow down and watch the city light up.',
        'orientation' => 'vertical',
        'style' => 'framed',
        'media' => ['media_id' => $items[3]['id']],
        'level' => 3,
      ]),
    ];
    return [
      $node('section', $section, [
        'main_slot' => [
          $node('hero-side-by-side', [
            'padding_block_start' => '32',
            'padding_block_end' => '32',
            'image_position' => 'right',
            'image_size' => '4:3',
            'image_radius' => 'extra-large',
            'media' => ['media_id' => $items[0]['id']],
          ] + ($byte ? [] : ['justify_content' => 'center']), [
            'hero_slot' => [
              $node('badge', ['label' => '48 hours. Endless discoveries.', 'style' => 'secondary']),
              $heading($title, 1),
              $node('text', [
                'text' => 'Follow the waterfront, find a new favourite café, and discover a city that keeps moving.',
                'text_size' => 'normal',
                'text_color' => 'default',
              ]),
              $node('button', [
                'label' => 'Plan your weekend',
                'href' => '#highlights',
                'variant' => 'primary',
                'size' => 'large',
                'icon' => 'arrow-right',
              ]),
            ],
          ]),
        ],
      ]),
      $node('section', array_replace($section, [
        'section_header' => TRUE,
        'columns' => $byte ? '33-33-33' : '100',
      ]), [
        'header_slot' => [$heading('Three ways to discover Rotterdam')],
        'main_slot' => $byte ? $cards : [
          $node('grid', ['columns' => '3 columns', 'mobile_columns' => '1', 'width' => '100%'], ['main_slot' => $cards]),
        ],
      ]),
      $node('cta', [
        'heading_text' => 'Make a weekend of it.',
        'text' => 'Your next discovery is just around the corner.',
        'level' => 2,
        'text_align' => 'center',
        'background_color' => 'muted',
      ] + ($byte ? [] : ['overlay_opacity' => '0%']), [
        'actions' => [
          $node('button', [
            'label' => 'Explore Rotterdam',
            'href' => 'https://www.rotterdam.info/',
            'variant' => 'primary',
            'size' => 'large',
          ]),
        ],
      ]),
    ];
  }

}
