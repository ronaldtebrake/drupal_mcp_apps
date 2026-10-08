<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_activity_demo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mcp_apps_activity_demo\ActivityData;

/**
 * Filters the website's shared activity components without changing content.
 */
final class ActivityFiltersForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcp_apps_activity_filters';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $days = 30, string $section = 'all', string $view = 'dashboard'): array {
    $form['#attributes']['class'][] = 'pulse-filters';
    $form['days'] = [
      '#type' => 'number',
      '#title' => $this->t('Last days'),
      '#default_value' => $days,
      '#min' => 1,
      '#max' => ActivityData::MAX_DAYS,
      '#step' => 1,
      '#required' => TRUE,
    ];
    $form['section'] = [
      '#type' => 'select',
      '#title' => $this->t('Section'),
      '#default_value' => $section,
      '#options' => [
        'all' => $this->t('All sections'),
        'guides' => $this->t('Guides'),
        'culture' => $this->t('Culture'),
      ],
    ];
    $form['view'] = ['#type' => 'value', '#value' => $view];
    $form['apply'] = ['#type' => 'submit', '#value' => $this->t('Apply filters')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('mcp_apps_activity_demo.website', [], [
      'query' => [
        'days' => (int) $form_state->getValue('days'),
        'section' => $form_state->getValue('section'),
        'view' => $form_state->getValue('view'),
      ],
    ]);
  }

}
