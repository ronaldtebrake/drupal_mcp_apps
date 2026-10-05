<?php

declare(strict_types=1);

namespace Drupal\mcp_apps\Mcp;

use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;

/**
 * A deliberately small hero-image workflow for unpublished demo posts.
 */
final class HeroWorkflow {

  public const BUNDLE = 'mcp_hero_demo';
  public const MEDIA_FIELD = 'field_mcp_hero_media';
  public const ALT_FIELD = 'field_mcp_hero_alt';

  /**
   * Lists viewable demo posts with their current hero and update capability.
   */
  public function posts(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', self::BUNDLE)->sort('changed', 'DESC')->range(0, 20)->execute();
    $posts = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if ($node->access('view') && $node->get('title')->access('view')) {
        $posts[] = $this->post($node);
      }
    }
    return $posts;
  }

  /**
   * Saves only a confirmed hero reference and post-specific alt text.
   */
  #[McpTool(
    name: 'media_picker_save_hero',
    title: 'Save a draft post hero image',
    description: 'After explicit user confirmation in the Media picker, save a new revision of an unpublished MCP hero demo post with a selected image Media ID and post-specific alt text. Requires its current revision token. Does not publish or modify shared Media alt text. Refuses published or moderated posts.',
    annotations: new ToolAnnotations(readOnlyHint: FALSE, destructiveHint: FALSE, openWorldHint: FALSE),
    meta: ['ui' => ['resourceUri' => MediaPicker::URI, 'visibility' => ['app']]],
  )]
  public function save(int $node_id, int $media_id, string $alt, string $revision): CallToolResult {
    return DemoSupport::respond(function () use ($node_id, $media_id, $alt, $revision): array {
      if ($node_id < 1 || $media_id < 1 || trim($alt) === '' || mb_strlen($alt) > 500) {
        throw new \InvalidArgumentException('Choose a post and image, and provide alt text of 1–500 characters.');
      }
      $lock = \Drupal::lock();
      $lock_key = 'mcp_apps:hero:' . $node_id;
      if (!$lock->acquire($lock_key, 30.0)) {
        throw new \InvalidArgumentException('Another hero update is in progress. Refresh before trying again.');
      }
      try {
        $storage = \Drupal::entityTypeManager()->getStorage('node');
        $storage->resetCache([$node_id]);
        $node = $storage->load($node_id);
        if (!$node instanceof NodeInterface || !$this->editable($node)) {
          throw new \InvalidArgumentException('This post is not an editable, unmoderated demo draft.');
        }
        if (!hash_equals($this->token($node), $revision)) {
          throw new \InvalidArgumentException('The post changed since you opened it. Refresh the picker and review the latest hero.');
        }
        $media = \Drupal::entityTypeManager()->getStorage('media')->load($media_id);
        if (!$media instanceof MediaInterface || !$media->access('view') || !$media->get('name')->access('view')) {
          throw new \InvalidArgumentException('The selected Media is unavailable.');
        }
        $type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media->bundle());
        $definition = $media->getSource()->getSourceFieldDefinition($type);
        if ($definition === NULL || $definition->getType() !== 'image') {
          throw new \InvalidArgumentException('Choose image Media for the hero.');
        }
        $field = $media->get($definition->getName());
        $file = $field->entity;
        if (!$field->access('view') || $file === NULL || !$file->access('view') || !str_starts_with($file->getFileUri(), 'public://')) {
          throw new \InvalidArgumentException('The selected image is unavailable to this account.');
        }
        $before = $this->values($node);
        $node->set(self::MEDIA_FIELD, ['target_id' => $media_id]);
        $node->set(self::ALT_FIELD, trim($alt));
        $node->setNewRevision(TRUE);
        $node->isDefaultRevision(TRUE);
        $node->setRevisionUserId((int) \Drupal::currentUser()->id());
        $node->setRevisionCreationTime(\Drupal::time()->getCurrentTime());
        $node->setRevisionLogMessage('Hero image and post-specific alt text updated from the MCP Media picker.');
        $violations = $node->validate();
        if (count($violations)) {
          throw new \InvalidArgumentException('Drupal rejected the hero change: ' . $violations->get(0)->getMessage());
        }
        $transaction = \Drupal::database()->startTransaction();
        try {
          $node->save();
          $storage->resetCache([$node_id]);
          $saved = $storage->load($node_id);
          $after = $this->values($saved);
          // Fail closed if a presave hook publishes or changes other content.
          $allowed_changes = [
            self::MEDIA_FIELD,
            self::ALT_FIELD,
            'vid',
            'revision_timestamp',
            'revision_uid',
            'revision_log',
            'changed',
            'revision_default',
            'revision_translation_affected',
          ];
          foreach ($allowed_changes as $name) {
            unset($before[$name], $after[$name]);
          }
          if ($saved->isPublished() || $before !== $after || (int) $saved->get(self::MEDIA_FIELD)->target_id !== $media_id || $saved->get(self::ALT_FIELD)->value !== trim($alt)) {
            throw new \InvalidArgumentException('Drupal could not save only the requested hero change while keeping the post unpublished.');
          }
        }
        catch (\Throwable $exception) {
          $transaction->rollBack();
          $storage->resetCache([$node_id]);
          throw $exception;
        }
        unset($transaction);
        return [
          'app' => 'media-picker-hero-saved',
          'post' => $this->post($saved),
          'message' => 'Hero image saved as a new revision. The post is still unpublished; shared Media alt text is unchanged.',
        ];
      }
      finally {
        $lock->release($lock_key);
      }
    });
  }

  /**
   * Exposes only the two fields that this small demo supports.
   */
  private function post(NodeInterface $node): array {
    $hero = NULL;
    if ($node->hasField(self::MEDIA_FIELD) && $node->get(self::MEDIA_FIELD)->access('view')) {
      $media = $node->get(self::MEDIA_FIELD)->entity;
      if ($media !== NULL && $media->access('view') && $media->get('name')->access('view')) {
        $hero = ['id' => (int) $media->id(), 'name' => $media->label()];
      }
    }
    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'summary' => $node->hasField('body') && $node->get('body')->access('view') ? trim(strip_tags((string) ($node->get('body')->summary ?: $node->get('body')->value))) : '',
      'byline' => 'Editorial team',
      'date' => gmdate('F j, Y', (int) $node->getCreatedTime()),
      'revision' => $this->token($node),
      'hero' => $hero,
      'alt' => $node->hasField(self::ALT_FIELD) && $node->get(self::ALT_FIELD)->access('view') ? (string) $node->get(self::ALT_FIELD)->value : '',
      'can_update' => $this->editable($node),
      'status' => $node->isPublished() ? 'published' : 'draft',
    ];
  }

  /**
   * Guards the workflow boundary and Drupal entity/field permissions.
   */
  private function editable(NodeInterface $node): bool {
    if ($node->bundle() !== self::BUNDLE || $node->isPublished() || !$node->access('view') || !$node->access('update')) {
      return FALSE;
    }
    foreach ([self::MEDIA_FIELD, self::ALT_FIELD] as $name) {
      if (!$node->hasField($name) || !$node->get($name)->access('view') || !$node->get($name)->access('edit')) {
        return FALSE;
      }
    }
    if ((string) \Drupal::entityTypeManager()->getStorage('node')->getLatestRevisionId($node->id()) !== (string) $node->getRevisionId()) {
      return FALSE;
    }
    return !\Drupal::hasService('content_moderation.moderation_information') || !\Drupal::service('content_moderation.moderation_information')->isModeratedEntity($node);
  }

  /**
   * Uses stored values and latest revision to reject stale changes.
   */
  private function token(NodeInterface $node): string {
    return hash('sha256', json_encode([
      $this->values($node),
      (string) \Drupal::entityTypeManager()->getStorage('node')->getLatestRevisionId($node->id()),
    ], JSON_THROW_ON_ERROR));
  }

  /**
   * Canonicalizes stored scalar values across storage/cache representations.
   */
  private function values(NodeInterface $node): array {
    $values = [];
    foreach ($node->getFieldDefinitions() as $name => $definition) {
      if (!$definition->isComputed()) {
        $values[$name] = $node->get($name)->getValue();
      }
    }
    $normalize = static function (mixed $value) use (&$normalize): mixed {
      if (!is_array($value)) {
        return $value === NULL ? NULL : (string) $value;
      }
      ksort($value);
      return array_map($normalize, $value);
    };
    return $normalize($values);
  }

}
