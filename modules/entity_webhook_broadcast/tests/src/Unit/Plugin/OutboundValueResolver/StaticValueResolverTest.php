<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Plugin\OutboundValueResolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\StaticValueResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the StaticValueResolver plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\StaticValueResolver
 * @group entity_webhook_broadcast
 */
class StaticValueResolverTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\StaticValueResolver
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): StaticValueResolver {
    return new StaticValueResolver(
      $configuration,
      'static_value',
      ['id' => 'static_value', 'label' => 'Static Value'],
    );
  }

  /**
   * Tests that resolve returns the configured static value regardless of entity.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsConfiguredStaticValue(): void {
    // Arrange
    $plugin = $this->createPlugin(['value' => 'published']);
    $entity = $this->createMock(EntityInterface::class);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertSame('published', $result);
  }

  /**
   * Tests that resolve ignores the entity content entirely.
   *
   * @covers ::resolve
   */
  public function testResolveIgnoresEntityContent(): void {
    // Arrange
    $plugin = $this->createPlugin(['value' => 'fixed']);
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->never())->method($this->anything());

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertSame('fixed', $result);
  }

  /**
   * Tests that resolve returns an empty string when configured with an empty value.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsEmptyStringWhenValueIsEmpty(): void {
    // Arrange
    $plugin = $this->createPlugin(['value' => '']);
    $entity = $this->createMock(EntityInterface::class);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertSame('', $result);
  }

  /**
   * Tests that defaultConfiguration provides an empty value key.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesEmptyValue(): void {
    // Arrange
    $plugin = $this->createPlugin();

    // Act
    $config = $plugin->defaultConfiguration();

    // Assert
    $this->assertSame(['value' => ''], $config);
  }

  /**
   * Tests that resolve returns an empty string when no configuration is provided.
   *
   * @covers ::resolve
   */
  public function testResolveUsesDefaultValueWhenNoneConfigured(): void {
    // Arrange
    $plugin = $this->createPlugin();
    $entity = $this->createMock(EntityInterface::class);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertSame('', $result);
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    // Arrange
    $plugin = $this->createPlugin();

    // Act
    $label = $plugin->label();

    // Assert
    $this->assertSame('Static Value', $label);
  }

}
