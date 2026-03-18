<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Entity;

use Drupal\entity_webhook\Entity\FieldMapping;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the FieldMapping value object.
 *
 * @group entity_webhook
 * @coversDefaultClass \Drupal\entity_webhook\Entity\FieldMapping
 */
class FieldMappingTest extends UnitTestCase {

  /**
   * Tests that FieldMapping stores all properties correctly.
   *
   * @covers ::__construct
   */
  public function testConstructorStoresProperties(): void {
    $mapping = new FieldMapping(
      entityField: 'field_email',
      isIdentifier: TRUE,
      resolver: 'json_path',
      resolverConfig: ['path' => '$.contact.email'],
    );

    $this->assertSame('field_email', $mapping->entityField);
    $this->assertSame(['path' => '$.contact.email'], $mapping->resolverConfig);
    $this->assertTrue($mapping->isIdentifier);
  }

  /**
   * Tests that isIdentifier defaults to FALSE.
   *
   * @covers ::__construct
   */
  public function testIsIdentifierDefaultsFalse(): void {
    $mapping = new FieldMapping(
      entityField: 'title',
      resolverConfig: ['path' => '$.name'],
    );

    $this->assertFalse($mapping->isIdentifier);
  }

  /**
   * Tests fromArray creates a FieldMapping from a configuration array.
   *
   * @covers ::fromArray
   */
  public function testFromArrayCreatesFieldMapping(): void {
    $data = [
      'entity_field' => 'field_external_id',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.id'],
    ];

    $mapping = FieldMapping::fromArray($data);

    $this->assertSame('field_external_id', $mapping->entityField);
    $this->assertSame(['path' => '$.id'], $mapping->resolverConfig);
    $this->assertTrue($mapping->isIdentifier);
  }

  /**
   * Tests fromArray handles missing keys with safe defaults.
   *
   * @covers ::fromArray
   */
  public function testFromArrayHandlesMissingKeysWithDefaults(): void {
    $mapping = FieldMapping::fromArray([]);

    $this->assertSame('', $mapping->entityField);
    $this->assertSame([], $mapping->resolverConfig);
    $this->assertFalse($mapping->isIdentifier);
  }

  /**
   * Tests toArray returns the expected structure for config storage.
   *
   * @covers ::toArray
   */
  public function testToArrayReturnsConfigStorageStructure(): void {
    $mapping = new FieldMapping(
      entityField: 'title',
      isIdentifier: FALSE,
      resolver: 'json_path',
      resolverConfig: ['path' => '$.name'],
    );

    $expected = [
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'mutation_plugin' => '',
      'mutation_config' => [],
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name'],
    ];

    $this->assertSame($expected, $mapping->toArray());
  }

  /**
   * Tests round-trip fromArray/toArray preserves all values.
   *
   * @covers ::fromArray
   * @covers ::toArray
   */
  public function testRoundTripPreservesValues(): void {
    $original = [
      'entity_field' => 'field_sku',
      'is_identifier' => TRUE,
      'mutation_plugin' => '',
      'mutation_config' => [],
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.product.sku'],
    ];

    $result = FieldMapping::fromArray($original)->toArray();

    $this->assertSame($original, $result);
  }

  /**
   * Tests that mutation fields default to empty values when absent from array.
   *
   * @covers ::fromArray
   */
  public function testFromArrayDefaultsToEmptyMutationValues(): void {
    $mapping = FieldMapping::fromArray([
      'entity_field' => 'title',
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name'],
    ]);

    $this->assertSame('', $mapping->mutationPlugin);
    $this->assertSame([], $mapping->mutationConfig);
  }

  /**
   * Tests that resolver fields default to json_path when absent from array.
   *
   * @covers ::fromArray
   */
  public function testFromArrayDefaultsToJsonPathResolver(): void {
    $mapping = FieldMapping::fromArray([
      'entity_field' => 'title',
    ]);

    $this->assertSame('json_path', $mapping->resolver);
    $this->assertSame([], $mapping->resolverConfig);
  }

  /**
   * Tests that resolver plugin and config are stored and returned correctly.
   *
   * @covers ::fromArray
   * @covers ::toArray
   */
  public function testResolverRoundTripPreservesValues(): void {
    $original = [
      'entity_field' => 'field_source',
      'is_identifier' => FALSE,
      'mutation_plugin' => '',
      'mutation_config' => [],
      'resolver' => 'static_value',
      'resolver_config' => ['value' => 'shopify'],
    ];

    $mapping = FieldMapping::fromArray($original);

    $this->assertSame('static_value', $mapping->resolver);
    $this->assertSame(['value' => 'shopify'], $mapping->resolverConfig);
    $this->assertSame($original, $mapping->toArray());
  }

  /**
   * Tests that mutation plugin and config are stored and returned correctly.
   *
   * @covers ::__construct
   * @covers ::fromArray
   * @covers ::toArray
   */
  public function testMutationPluginRoundTripPreservesValues(): void {
    $original = [
      'entity_field' => 'field_price',
      'is_identifier' => FALSE,
      'mutation_plugin' => 'currency_convert',
      'mutation_config' => ['from' => 'USD', 'to' => 'EUR'],
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.price'],
    ];

    $mapping = FieldMapping::fromArray($original);

    $this->assertSame('currency_convert', $mapping->mutationPlugin);
    $this->assertSame(['from' => 'USD', 'to' => 'EUR'], $mapping->mutationConfig);
    $this->assertSame($original, $mapping->toArray());
  }

}
