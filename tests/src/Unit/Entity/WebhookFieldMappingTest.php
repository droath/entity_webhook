<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Entity;

use Drupal\entity_webhook\Entity\WebhookFieldMapping;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for WebhookFieldMapping toFieldMapping() adapter method.
 *
 * @group entity_webhook
 * @coversDefaultClass \Drupal\entity_webhook\Entity\WebhookFieldMapping
 */
class WebhookFieldMappingTest extends UnitTestCase {

  /**
   * Creates a partially constructed WebhookFieldMapping for unit testing.
   *
   * @param array<string, mixed> $values
   *   Property values to set on the entity.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookFieldMapping
   *   The entity instance with properties set via reflection.
   */
  private function createMapping(array $values): WebhookFieldMapping {
    $mapping = $this->getMockBuilder(WebhookFieldMapping::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();

    $reflection = new \ReflectionClass($mapping);
    foreach ($values as $property => $value) {
      $prop = $reflection->getProperty($property);
      $prop->setValue($mapping, $value);
    }

    return $mapping;
  }

  /**
   * Tests that toFieldMapping returns a FieldMapping with all properties mapped.
   *
   * @covers ::toFieldMapping
   */
  public function testToFieldMappingReturnsCorrectValueObject(): void {
    // Arrange
    $mapping = $this->createMapping([
      'entity_field' => 'field_external_id',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.id'],
      'mutation_plugin' => 'string_replace',
      'mutation_config' => ['search' => 'foo', 'replace' => 'bar'],
    ]);

    // Act
    $fieldMapping = $mapping->toFieldMapping();

    // Assert
    $this->assertSame('field_external_id', $fieldMapping->entityField);
    $this->assertTrue($fieldMapping->isIdentifier);
    $this->assertSame('json_path', $fieldMapping->resolver);
    $this->assertSame(['path' => '$.id'], $fieldMapping->resolverConfig);
    $this->assertSame('string_replace', $fieldMapping->mutationPlugin);
    $this->assertSame(['search' => 'foo', 'replace' => 'bar'], $fieldMapping->mutationConfig);
  }

  /**
   * Tests that toFieldMapping works correctly when no mutation plugin is set.
   *
   * @covers ::toFieldMapping
   */
  public function testToFieldMappingWithEmptyMutationPlugin(): void {
    // Arrange
    $mapping = $this->createMapping([
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'static_value',
      'resolver_config' => ['value' => 'shopify'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ]);

    // Act
    $fieldMapping = $mapping->toFieldMapping();

    // Assert
    $this->assertSame('', $fieldMapping->mutationPlugin);
    $this->assertSame([], $fieldMapping->mutationConfig);
    $this->assertSame('title', $fieldMapping->entityField);
  }

  /**
   * Tests that toFieldMapping uses the default json_path resolver correctly.
   *
   * @covers ::toFieldMapping
   */
  public function testToFieldMappingWithDefaultResolver(): void {
    // Arrange
    $mapping = $this->createMapping([
      'entity_field' => 'body',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.description'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ]);

    // Act
    $fieldMapping = $mapping->toFieldMapping();

    // Assert
    $this->assertSame('json_path', $fieldMapping->resolver);
    $this->assertSame(['path' => '$.description'], $fieldMapping->resolverConfig);
  }

  /**
   * Tests that all getter methods return the correct typed values.
   *
   * @covers ::getEntityField
   * @covers ::isIdentifier
   * @covers ::getResolver
   * @covers ::getResolverConfig
   * @covers ::getMutationPlugin
   * @covers ::getMutationConfig
   * @covers ::getSourceTypeId
   */
  public function testGettersReturnCorrectValues(): void {
    // Arrange
    $mapping = $this->createMapping([
      'source_type' => 'shopify_order',
      'entity_field' => 'field_sku',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.sku'],
      'mutation_plugin' => 'price_cents_to_decimal',
      'mutation_config' => ['precision' => 2],
    ]);

    // Assert
    $this->assertSame('shopify_order', $mapping->getSourceTypeId());
    $this->assertSame('field_sku', $mapping->getEntityField());
    $this->assertTrue($mapping->isIdentifier());
    $this->assertSame('json_path', $mapping->getResolver());
    $this->assertSame(['path' => '$.sku'], $mapping->getResolverConfig());
    $this->assertSame('price_cents_to_decimal', $mapping->getMutationPlugin());
    $this->assertSame(['precision' => 2], $mapping->getMutationConfig());
  }

}
