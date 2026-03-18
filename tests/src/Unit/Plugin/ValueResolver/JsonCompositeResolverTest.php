<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\ValueResolver;

use Drupal\entity_webhook\Plugin\ValueResolver\JsonCompositeResolver;
use Drupal\entity_webhook\Service\JsonPathExtractorInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the JsonCompositeResolver plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\ValueResolver\JsonCompositeResolver
 * @group entity_webhook
 */
class JsonCompositeResolverTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param \Drupal\entity_webhook\Service\JsonPathExtractorInterface|null $extractor
   *   Optional extractor mock; a no-op mock is used if not provided.
   *
   * @return \Drupal\entity_webhook\Plugin\ValueResolver\JsonCompositeResolver
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = [], ?JsonPathExtractorInterface $extractor = NULL): JsonCompositeResolver {
    $extractor ??= $this->createMock(JsonPathExtractorInterface::class);

    return new JsonCompositeResolver(
      $configuration,
      'json_composite',
      ['id' => 'json_composite', 'label' => 'JSON Composite'],
      $extractor,
    );
  }

  /**
   * Tests that resolve builds a keyed array from multiple JSONPath expressions.
   *
   * @covers ::resolve
   */
  public function testResolveBuildsMappedArrayFromMultiplePaths(): void {
    $payload = ['subtotal_price' => '9.99', 'total_tax' => '1.00'];

    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->method('extract')
      ->willReturnMap([
        [$payload, '$.subtotal_price', '9.99'],
        [$payload, '$.total_tax', '1.00'],
      ]);

    $plugin = $this->createPlugin([
      'mapping' => [
        'subtotal' => '$.subtotal_price',
        'tax' => '$.total_tax',
      ],
    ], $extractor);

    $result = $plugin->resolve($payload);

    $this->assertSame([
      'subtotal' => '9.99',
      'tax' => '1.00',
    ], $result);
  }

  /**
   * Tests that resolve returns NULL when mapping is empty.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenMappingIsEmpty(): void {
    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->expects($this->never())->method('extract');

    $plugin = $this->createPlugin(['mapping' => []], $extractor);

    $this->assertNull($plugin->resolve(['foo' => 'bar']));
  }

  /**
   * Tests that resolve sets NULL for keys whose path matches nothing in the payload.
   *
   * @covers ::resolve
   */
  public function testResolveUsesNullForUnmatchedPaths(): void {
    $payload = ['price' => '10.00'];

    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->method('extract')
      ->willReturnMap([
        [$payload, '$.price', '10.00'],
        [$payload, '$.nonexistent', NULL],
      ]);

    $plugin = $this->createPlugin([
      'mapping' => [
        'price' => '$.price',
        'missing' => '$.nonexistent',
      ],
    ], $extractor);

    $result = $plugin->resolve($payload);

    $this->assertSame([
      'price' => '10.00',
      'missing' => NULL,
    ], $result);
  }

  /**
   * Tests that resolve returns NULL when using defaultConfiguration mapping.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWithDefaultConfiguration(): void {
    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->expects($this->never())->method('extract');

    $plugin = $this->createPlugin([], $extractor);

    $this->assertNull($plugin->resolve(['foo' => 'bar']));
  }

  /**
   * Tests that defaultConfiguration provides an empty mapping.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesEmptyMapping(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(['mapping' => []], $plugin->defaultConfiguration());
  }

  /**
   * Tests that resolve calls extract once per mapping entry.
   *
   * @covers ::resolve
   */
  public function testResolveCallsExtractForEachMappingEntry(): void {
    $payload = ['a' => 1, 'b' => 2, 'c' => 3];

    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->expects($this->exactly(3))
      ->method('extract')
      ->willReturn(1);

    $plugin = $this->createPlugin([
      'mapping' => [
        'x' => '$.a',
        'y' => '$.b',
        'z' => '$.c',
      ],
    ], $extractor);

    $plugin->resolve($payload);
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('JSON Composite', $plugin->label());
  }

}
