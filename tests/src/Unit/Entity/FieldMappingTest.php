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
      jsonPath: '$.contact.email',
      isIdentifier: TRUE,
    );

    $this->assertSame('field_email', $mapping->entityField);
    $this->assertSame('$.contact.email', $mapping->jsonPath);
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
      jsonPath: '$.name',
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
      'json_path' => '$.id',
      'is_identifier' => TRUE,
    ];

    $mapping = FieldMapping::fromArray($data);

    $this->assertSame('field_external_id', $mapping->entityField);
    $this->assertSame('$.id', $mapping->jsonPath);
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
    $this->assertSame('', $mapping->jsonPath);
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
      jsonPath: '$.name',
      isIdentifier: FALSE,
    );

    $expected = [
      'entity_field' => 'title',
      'json_path' => '$.name',
      'is_identifier' => FALSE,
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
      'json_path' => '$.product.sku',
      'is_identifier' => TRUE,
    ];

    $result = FieldMapping::fromArray($original)->toArray();

    $this->assertSame($original, $result);
  }

}
