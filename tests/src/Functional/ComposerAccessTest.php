<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp_apps\Functional;

use Drupal\user\Entity\Role;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\media\Entity\Media;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal MCP Apps integration.
 */
#[Group('mcp_apps')]
#[RunTestsInSeparateProcesses]
final class ComposerAccessTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mcp_apps_openui', 'mcp_apps_test', 'media', 'image'];
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests browser and media access.
   */
  public function testBrowserAndMediaAccess(): void {
    foreach (['anonymous', 'authenticated'] as $role) {
      Role::load($role)->revokePermission('view media')->save();
    }
    $this->drupalGet('/admin/content/mcp-apps/composer');
    $this->assertSession()->statusCodeEquals(403);
    $denied = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($denied);
    $this->drupalGet('/admin/content/mcp-apps/composer');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogout();
    $allowed = $this->drupalCreateUser(['use mcp apps composer', 'view media', 'access content']);
    $this->drupalLogin($allowed);
    $this->drupalGet('/admin/content/mcp-apps/composer');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('__DRUPAL_BROWSER_PREVIEW__');
    preg_match('/window\.__DRUPAL_BROWSER_PREVIEW__=(.+?);<\/script>/', $this->getSession()->getPage()->getContent(), $matches);
    $boot = json_decode($matches[1], TRUE, 512, JSON_THROW_ON_ERROR);
    $client = $this->getSession()->getDriver()->getClient();
    $input = json_encode([
      'name' => 'component_composer_preview',
      'arguments' => [
        'session_id' => $boot['result']['structuredContent']['data']['session_id'],
        'composition' => json_encode([['component' => 'mcp_apps_test:text', 'props' => ['text' => 'Browser preview']]]),
      ],
    ]);
    $client->request('POST', $this->buildUrl('/admin/content/mcp-apps/composer/call'), [], [], ['CONTENT_TYPE' => 'application/json'], $input);
    $this->assertSession()->statusCodeEquals(403);
    $client->request('POST', $this->buildUrl('/admin/content/mcp-apps/composer/call'), [], [], [
      'CONTENT_TYPE' => 'application/json',
      'HTTP_X_CSRF_TOKEN' => $boot['token'],
    ], $input);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Browser preview');

    $type = $this->createMediaType('image');
    $field = $type->getSource()->getSourceFieldDefinition($type)->getName();
    $file = $this->container->get('file.repository')->writeData('Public test image', 'public://access-test.jpg');
    $media = Media::create([
      'bundle' => $type->id(),
      'name' => 'Accessible image',
      'status' => 1,
      $field => ['target_id' => $file->id(), 'alt' => 'Example', 'width' => 10, 'height' => 10],
    ]);
    $media->save();
    $switcher = $this->container->get('account_switcher');
    $composition = $this->container->get('mcp_apps_openui.composition');
    $switcher->switchTo($allowed);
    try {
      $this->assertSame('Example', $composition->image((int) $media->id())['alt']);
    }
    finally {
      $switcher->switchBack();
    }
    $fieldDenied = $this->drupalCreateUser(['view media', 'access content', 'deny test image field']);
    foreach ([new AnonymousUserSession(), $denied, $fieldDenied] as $account) {
      $switcher->switchTo($account);
      try {
        try {
          $composition->image((int) $media->id());
          $this->fail('Denied Media leaked.');
        }
        catch (\InvalidArgumentException) {
        }
      }
      finally {
        $switcher->switchBack();
      }
    }
    $file->setFileUri('private://access-test.jpg');
    $file->save();
    $this->container->get('entity_type.manager')->getStorage('file')->resetCache();
    $this->container->get('entity_type.manager')->getStorage('media')->resetCache();
    $switcher->switchTo($allowed);
    try {
      $this->expectException(\InvalidArgumentException::class);
      $composition->image((int) $media->id());
    }
    finally {
      $switcher->switchBack();
    }
  }

}
