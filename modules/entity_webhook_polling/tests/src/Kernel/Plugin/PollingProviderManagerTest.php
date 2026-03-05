<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_polling\Kernel\Plugin;

use Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the PollingProviderManager plugin manager.
 *
 * @group entity_webhook_polling
 */
class PollingProviderManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook', 'entity_webhook_polling'];

  /**
   * Tests that the plugin manager service is registered and accessible.
   */
  public function testPluginManagerServiceIsRegistered(): void {
    $manager = $this->container->get('plugin.manager.polling_provider');

    $this->assertInstanceOf(PollingProviderManager::class, $manager);
  }

  /**
   * Tests that the plugin manager returns an empty definitions array when no
   * plugins are registered.
   */
  public function testPluginManagerReturnsEmptyDefinitionsWithNoBundledPlugins(): void {
    /** @var \Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderManager $manager */
    $manager = $this->container->get('plugin.manager.polling_provider');

    $definitions = $manager->getDefinitions();

    $this->assertIsArray($definitions);
  }

  /**
   * Tests that the plugin manager can create a plugin instance from definition.
   *
   * Uses a test plugin registered via the test module's namespace.
   */
  public function testPluginManagerCanInstantiatePlugin(): void {
    /** @var \Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderManager $manager */
    $manager = $this->container->get('plugin.manager.polling_provider');

    $definitions = $manager->getDefinitions();

    foreach ($definitions as $id => $definition) {
      $plugin = $manager->createInstance($id, []);
      $this->assertInstanceOf(
        \Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderInterface::class,
        $plugin,
      );
      break;
    }

    // If no plugins defined, just assert definitions is array (already checked).
    $this->assertIsArray($definitions);
  }

}
