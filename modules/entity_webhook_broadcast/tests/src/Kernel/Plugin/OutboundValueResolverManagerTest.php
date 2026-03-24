<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Plugin;

use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\EntityFieldResolver;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\EntityReferenceFieldResolver;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\FieldComponentResolver;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverInterface;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManager;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\StaticValueResolver;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the OutboundValueResolverManager plugin manager.
 *
 * @group entity_webhook_broadcast
 */
class OutboundValueResolverManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook', 'entity_webhook_broadcast'];

  /**
   * Tests that the plugin manager service is registered and accessible.
   */
  public function testPluginManagerServiceIsRegistered(): void {
    // Act
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Assert
    $this->assertInstanceOf(OutboundValueResolverManager::class, $manager);
  }

  /**
   * Tests that the plugin manager discovers all bundled resolver plugins.
   */
  public function testPluginManagerDiscoversAllBundledPlugins(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $definitions = $manager->getDefinitions();

    // Assert
    $this->assertArrayHasKey('entity_field', $definitions);
    $this->assertArrayHasKey('static_value', $definitions);
    $this->assertArrayHasKey('entity_reference_field', $definitions);
    $this->assertArrayHasKey('field_component', $definitions);
  }

  /**
   * Tests that getOptions() returns an associative array of plugin IDs to labels.
   */
  public function testGetOptionsReturnsAssociativeArrayOfPluginIdsToLabels(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $options = $manager->getOptions();

    // Assert
    $this->assertIsArray($options);
    $this->assertNotEmpty($options);

    foreach ($options as $pluginId => $label) {
      $this->assertIsString($pluginId, 'Each key must be a string plugin ID.');
      $this->assertIsString($label, 'Each value must be a plain string label.');
    }
  }

  /**
   * Tests that getOptions() includes all bundled plugin IDs.
   */
  public function testGetOptionsIncludesAllBundledPluginIds(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $options = $manager->getOptions();

    // Assert
    $this->assertArrayHasKey('entity_field', $options);
    $this->assertArrayHasKey('static_value', $options);
    $this->assertArrayHasKey('entity_reference_field', $options);
    $this->assertArrayHasKey('field_component', $options);
  }

  /**
   * Tests that getOptions() returns labels cast to plain strings, not TranslatableMarkup.
   */
  public function testGetOptionsLabelsArePlainStrings(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $options = $manager->getOptions();

    // Assert
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
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $options = $manager->getOptions();
    $labels = array_values($options);
    $sorted = $labels;
    sort($sorted);

    // Assert
    $this->assertSame($sorted, $labels, 'Options must be sorted alphabetically by label.');
  }

  /**
   * Tests that createInstance() returns a StaticValueResolver for the static_value plugin.
   */
  public function testCreateInstanceReturnsStaticValueResolverPlugin(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $plugin = $manager->createInstance('static_value', ['value' => 'hello']);

    // Assert
    $this->assertInstanceOf(OutboundValueResolverInterface::class, $plugin);
    $this->assertInstanceOf(StaticValueResolver::class, $plugin);
  }

  /**
   * Tests that createInstance() returns an EntityFieldResolver for the entity_field plugin.
   */
  public function testCreateInstanceReturnsEntityFieldResolverPlugin(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $plugin = $manager->createInstance('entity_field', ['entity_field' => 'title']);

    // Assert
    $this->assertInstanceOf(OutboundValueResolverInterface::class, $plugin);
    $this->assertInstanceOf(EntityFieldResolver::class, $plugin);
  }

  /**
   * Tests that the StaticValueResolver resolves the configured value at kernel level.
   */
  public function testStaticValueResolverResolvesConfiguredValueViaManager(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    $configuredValue = 'kernel-resolved-value';

    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\StaticValueResolver $plugin */
    $plugin = $manager->createInstance('static_value', ['value' => $configuredValue]);

    $entity = $this->createMock(\Drupal\Core\Entity\EntityInterface::class);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertSame($configuredValue, $result);
  }

  /**
   * Tests that createInstance() returns an EntityReferenceFieldResolver plugin.
   */
  public function testCreateInstanceReturnsEntityReferenceFieldResolverPlugin(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $plugin = $manager->createInstance('entity_reference_field', ['entity_field' => 'field_ref', 'target_field' => 'title']);

    // Assert
    $this->assertInstanceOf(OutboundValueResolverInterface::class, $plugin);
    $this->assertInstanceOf(EntityReferenceFieldResolver::class, $plugin);
  }

  /**
   * Tests that createInstance() returns a FieldComponentResolver plugin.
   */
  public function testCreateInstanceReturnsFieldComponentResolverPlugin(): void {
    // Arrange
    /** @var \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Act
    $plugin = $manager->createInstance('field_component', ['entity_field' => 'field_price', 'component' => 'number']);

    // Assert
    $this->assertInstanceOf(OutboundValueResolverInterface::class, $plugin);
    $this->assertInstanceOf(FieldComponentResolver::class, $plugin);
  }

  /**
   * Tests that the plugin manager implements OutboundValueResolverManagerInterface.
   */
  public function testPluginManagerImplementsManagerInterface(): void {
    // Act
    $manager = $this->container->get('plugin.manager.outbound_value_resolver');

    // Assert
    $this->assertInstanceOf(OutboundValueResolverManagerInterface::class, $manager);
  }

}
