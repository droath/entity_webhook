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

  /**
   * Tests that getOptions() returns an associative array of plugin IDs to labels.
   */
  public function testGetOptionsReturnsAssociativeArrayOfPluginIdsToLabels(): void {
    /** @var \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.webhook_verification');

    $options = $manager->getOptions();

    $this->assertIsArray($options);
    $this->assertNotEmpty($options);

    foreach ($options as $pluginId => $label) {
      $this->assertIsString($pluginId, 'Each key must be a string plugin ID.');
      $this->assertIsString($label, 'Each value must be a plain string label.');
    }
  }

  /**
   * Tests that getOptions() contains all three bundled plugin IDs.
   */
  public function testGetOptionsContainsAllThreeBundledPlugins(): void {
    /** @var \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.webhook_verification');

    $options = $manager->getOptions();

    $this->assertArrayHasKey('hmac_verification', $options);
    $this->assertArrayHasKey('api_key_verification', $options);
    $this->assertArrayHasKey('domain_whitelist_verification', $options);
  }

  /**
   * Tests that getOptions() returns labels as plain strings, not TranslatableMarkup objects.
   */
  public function testGetOptionsLabelsArePlainStrings(): void {
    /** @var \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.webhook_verification');

    $options = $manager->getOptions();

    foreach ($options as $label) {
      $this->assertNotInstanceOf(
        \Drupal\Core\StringTranslation\TranslatableMarkup::class,
        $label,
        'Labels must be cast to plain strings by getOptions().',
      );
    }
  }

  /**
   * Tests that getOptions() returns options sorted alphabetically by label.
   */
  public function testGetOptionsIsSortedAlphabeticallyByLabel(): void {
    /** @var \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.webhook_verification');

    $options = $manager->getOptions();
    $labels = array_values($options);
    $sorted = $labels;
    sort($sorted);

    $this->assertSame($sorted, $labels, 'Labels must be sorted alphabetically.');
  }

  /**
   * Tests that getOptions() label values match the plugin definitions' labels.
   */
  public function testGetOptionsLabelsMatchPluginDefinitions(): void {
    /** @var \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManager $manager */
    $manager = $this->container->get('plugin.manager.webhook_verification');

    $options = $manager->getOptions();
    $definitions = $manager->getDefinitions();

    foreach ($options as $pluginId => $label) {
      $this->assertSame(
        (string) $definitions[$pluginId]['label'],
        $label,
        "Label for plugin '$pluginId' must match its definition label.",
      );
    }
  }

}
