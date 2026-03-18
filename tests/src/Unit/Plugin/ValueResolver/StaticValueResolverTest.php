<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\ValueResolver;

use Drupal\entity_webhook\Plugin\ValueResolver\StaticValueResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the StaticValueResolver plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\ValueResolver\StaticValueResolver
 * @group entity_webhook
 */
class StaticValueResolverTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\ValueResolver\StaticValueResolver
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
   * Tests that resolve returns the configured static value regardless of payload.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsConfiguredStaticValue(): void {
    $plugin = $this->createPlugin(['value' => 'published']);

    $this->assertSame('published', $plugin->resolve(['anything' => 'here']));
  }

  /**
   * Tests that resolve ignores the payload content entirely.
   *
   * @covers ::resolve
   */
  public function testResolveIgnoresPayloadContent(): void {
    $plugin = $this->createPlugin(['value' => 'fixed']);

    $this->assertSame('fixed', $plugin->resolve([]));
    $this->assertSame('fixed', $plugin->resolve(['key' => 'value']));
    $this->assertSame('fixed', $plugin->resolve(['nested' => ['deep' => 'data']]));
  }

  /**
   * Tests that resolve returns an empty string when configured with an empty value.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsEmptyStringWhenValueIsEmpty(): void {
    $plugin = $this->createPlugin(['value' => '']);

    $this->assertSame('', $plugin->resolve(['foo' => 'bar']));
  }

  /**
   * Tests that defaultConfiguration provides an empty value.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesEmptyValue(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(['value' => ''], $plugin->defaultConfiguration());
  }

  /**
   * Tests that defaultConfiguration is used when no value is provided.
   *
   * @covers ::resolve
   */
  public function testResolveUsesDefaultValueWhenNoneConfigured(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('', $plugin->resolve(['foo' => 'bar']));
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('Static Value', $plugin->label());
  }

}
