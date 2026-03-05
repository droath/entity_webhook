<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Plugin;

use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the WebhookVerificationManager plugin manager.
 *
 * @group entity_webhook
 */
class WebhookVerificationManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook'];

  /**
   * Tests that the plugin manager service is registered and accessible.
   */
  public function testPluginManagerServiceIsRegistered(): void {
    $manager = $this->container->get('plugin.manager.webhook_verification');

    $this->assertSame(WebhookVerificationManager::class, get_class($manager));
  }

  /**
   * Tests that the plugin manager discovers the three bundled verification plugins.
   */
  public function testPluginManagerDiscoversBundledPlugins(): void {
    /** @var \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManager $manager */
    $manager = $this->container->get('plugin.manager.webhook_verification');

    $definitions = $manager->getDefinitions();

    $this->assertArrayHasKey('hmac_verification', $definitions);
    $this->assertArrayHasKey('domain_whitelist_verification', $definitions);
    $this->assertArrayHasKey('api_key_verification', $definitions);
  }

}
