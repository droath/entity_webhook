<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\FieldValueMutation;

use Drupal\entity_webhook\Plugin\FieldValueMutation\MapValues;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the MapValues plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\FieldValueMutation\MapValues
 * @group entity_webhook
 */
class MapValuesTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\FieldValueMutation\MapValues
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): MapValues {
    return new MapValues(
      $configuration,
      'map_values',
      ['id' => 'map_values', 'label' => 'Map Values'],
    );
  }

  /**
   * Tests that mutate maps a matching source value to its configured target.
   *
   * @covers ::mutate
   */
  public function testMutateMapsSourceValueToTargetValue(): void {
    $plugin = $this->createPlugin([
      'mapping' => ['paid' => 'completed', 'pending' => 'processing'],
      'fallback' => '',
    ]);

    $this->assertSame('completed', $plugin->mutate('paid'));
    $this->assertSame('processing', $plugin->mutate('pending'));
  }

  /**
   * Tests that mutate returns the original value when no mapping matches and fallback is empty.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsOriginalValueWhenNoMappingMatchesAndFallbackIsEmpty(): void {
    $plugin = $this->createPlugin([
      'mapping' => ['paid' => 'completed'],
      'fallback' => '',
    ]);

    $this->assertSame('unknown', $plugin->mutate('unknown'));
  }

  /**
   * Tests that mutate returns the fallback value when no mapping matches and fallback is set.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsFallbackValueWhenNoMappingMatches(): void {
    $plugin = $this->createPlugin([
      'mapping' => ['paid' => 'completed'],
      'fallback' => 'draft',
    ]);

    $this->assertSame('draft', $plugin->mutate('unknown_status'));
  }

  /**
   * Tests that mutate maps integer values by casting to string for lookup.
   *
   * @covers ::mutate
   */
  public function testMutateMapsIntegerValueByStringCast(): void {
    $plugin = $this->createPlugin([
      'mapping' => ['1' => 'active', '0' => 'inactive'],
      'fallback' => '',
    ]);

    $this->assertSame('active', $plugin->mutate(1));
    $this->assertSame('inactive', $plugin->mutate(0));
  }

  /**
   * Tests that mutate returns non-string non-integer values unchanged.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsNonStringNonIntegerValuesUnchanged(): void {
    $plugin = $this->createPlugin([
      'mapping' => ['paid' => 'completed'],
      'fallback' => 'fallback',
    ]);

    $this->assertNull($plugin->mutate(NULL));
    $this->assertTrue($plugin->mutate(TRUE));
    $this->assertSame(['array' => 'value'], $plugin->mutate(['array' => 'value']));
  }

  /**
   * Tests that defaultConfiguration provides an empty mapping and empty fallback.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesEmptyMappingAndFallback(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(['mapping' => [], 'fallback' => ''], $plugin->defaultConfiguration());
  }

  /**
   * Tests that mutate returns the original value when mapping is empty and no fallback is set.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsOriginalValueWhenMappingIsEmptyAndNoFallback(): void {
    $plugin = $this->createPlugin(['mapping' => [], 'fallback' => '']);

    $this->assertSame('anything', $plugin->mutate('anything'));
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('Map Values', $plugin->label());
  }

}
