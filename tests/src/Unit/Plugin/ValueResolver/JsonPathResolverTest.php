<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\ValueResolver;

use Drupal\entity_webhook\Plugin\ValueResolver\JsonPathResolver;
use Drupal\entity_webhook\Service\JsonPathExtractorInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the JsonPathResolver plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\ValueResolver\JsonPathResolver
 * @group entity_webhook
 */
class JsonPathResolverTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param \Drupal\entity_webhook\Service\JsonPathExtractorInterface|null $extractor
   *   Optional extractor mock; a no-op mock is used if not provided.
   *
   * @return \Drupal\entity_webhook\Plugin\ValueResolver\JsonPathResolver
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = [], ?JsonPathExtractorInterface $extractor = NULL): JsonPathResolver {
    $extractor ??= $this->createMock(JsonPathExtractorInterface::class);

    return new JsonPathResolver(
      $configuration,
      'json_path',
      ['id' => 'json_path', 'label' => 'JSONPath Expression'],
      $extractor,
    );
  }

  /**
   * Tests that resolve extracts a scalar value from the payload.
   *
   * @covers ::resolve
   */
  public function testResolveExtractsScalarValueFromPayload(): void {
    $payload = ['order' => ['id' => 42]];

    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->method('extract')
      ->with($payload, '$.order.id')
      ->willReturn(42);

    $plugin = $this->createPlugin(['path' => '$.order.id'], $extractor);

    $this->assertSame(42, $plugin->resolve($payload));
  }

  /**
   * Tests that resolve returns NULL when the path configuration is empty.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenPathIsEmpty(): void {
    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->expects($this->never())->method('extract');

    $plugin = $this->createPlugin(['path' => ''], $extractor);

    $this->assertNull($plugin->resolve(['foo' => 'bar']));
  }

  /**
   * Tests that resolve returns NULL when the extractor finds no match.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenExtractorFindsNoMatch(): void {
    $payload = ['order' => ['id' => 42]];

    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->method('extract')
      ->with($payload, '$.nonexistent.key')
      ->willReturn(NULL);

    $plugin = $this->createPlugin(['path' => '$.nonexistent.key'], $extractor);

    $this->assertNull($plugin->resolve($payload));
  }

  /**
   * Tests that resolve returns an array when the extractor returns multiple matches.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsArrayForMultiMatchExpression(): void {
    $payload = ['tags' => ['a', 'b', 'c']];

    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->method('extract')
      ->with($payload, '$.tags[*]')
      ->willReturn(['a', 'b', 'c']);

    $plugin = $this->createPlugin(['path' => '$.tags[*]'], $extractor);

    $this->assertSame(['a', 'b', 'c'], $plugin->resolve($payload));
  }

  /**
   * Tests that defaultConfiguration provides an empty path.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesEmptyPath(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(['path' => ''], $plugin->defaultConfiguration());
  }

  /**
   * Tests that the path from configuration is used when resolving.
   *
   * @covers ::resolve
   */
  public function testResolveUsesPathFromConfiguration(): void {
    $payload = ['customer' => ['email' => 'test@example.com']];

    $extractor = $this->createMock(JsonPathExtractorInterface::class);
    $extractor->expects($this->once())
      ->method('extract')
      ->with($payload, '$.customer.email')
      ->willReturn('test@example.com');

    $plugin = $this->createPlugin(['path' => '$.customer.email'], $extractor);

    $this->assertSame('test@example.com', $plugin->resolve($payload));
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('JSONPath Expression', $plugin->label());
  }

}
