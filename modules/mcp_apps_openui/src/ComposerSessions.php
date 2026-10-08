<?php

declare(strict_types=1);

namespace Drupal\mcp_apps_openui;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Account-bound, expiring preview state independent of browser cookies.
 */
final class ComposerSessions {

  public function __construct(
    private readonly KeyValueExpirableFactoryInterface $stores,
    private readonly AccountProxyInterface $account,
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * Creates an account-bound preview session.
   */
  public function create(array $data): string {
    $id = bin2hex(random_bytes(24));
    $this->put($id, $data);
    return $id;
  }

  /**
   * Returns preview state belonging to the current account.
   */
  public function get(string $id): array {
    $data = $this->stores->get('mcp_apps_composer')->get($this->key($id));
    if (!is_array($data)) {
      throw new \InvalidArgumentException('This preview expired or belongs to another account. Open the composer again.');
    }
    return $data;
  }

  /**
   * Stores preview state with a bounded lifetime.
   */
  public function put(string $id, array $data): void {
    $this->stores->get('mcp_apps_composer')->setWithExpire($this->key($id), $data, 3600);
  }

  /**
   * Serializes preview changes for one session.
   */
  public function synchronized(string $id, callable $operation): mixed {
    $key = 'mcp_apps:' . $this->key($id);
    if (!$this->lock->acquire($key, 60)) {
      throw new \InvalidArgumentException('Another preview is in progress. Try again.');
    }
    try {
      return $operation($this->get($id));
    }
    finally {
      $this->lock->release($key);
    }
  }

  /**
   * Builds the authenticated account-specific session key.
   */
  private function key(string $id): string {
    if (!$this->account->isAuthenticated() || !preg_match('/^[a-f0-9]{48}$/D', $id)) {
      throw new \InvalidArgumentException('An authenticated composer session is required.');
    }
    return $this->account->id() . ':' . $id;
  }

}
