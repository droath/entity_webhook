<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Service;

use Drupal\entity_webhook\Service\JsonPathExtractor;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for JsonPathExtractor service.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Service\JsonPathExtractor
 * @group entity_webhook
 */
class JsonPathExtractorTest extends UnitTestCase {

  private JsonPathExtractor $extractor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->extractor = new JsonPathExtractor();
  }

  /**
   * Tests scalar value extraction from a simple JSONPath expression.
   *
   * @covers ::extract
   */
  public function testExtractScalarValueFromTopLevelKey(): void {
    $payload = ['name' => 'John Doe', 'email' => 'john@example.com'];

    $result = $this->extractor->extract($payload, '$.name');

    $this->assertSame('John Doe', $result);
  }

  /**
   * Tests extraction of a nested value using dot notation.
   *
   * @covers ::extract
   */
  public function testExtractNestedValueUsingDotNotation(): void {
    $payload = [
      'user' => [
        'address' => [
          'city' => 'Portland',
        ],
      ],
    ];

    $result = $this->extractor->extract($payload, '$.user.address.city');

    $this->assertSame('Portland', $result);
  }

  /**
   * Tests that array values are returned when the expression matches multiple elements.
   *
   * @covers ::extract
   */
  public function testExtractArrayFromWildcardExpression(): void {
    $payload = [
      'tags' => ['php', 'drupal', 'webhook'],
    ];

    $result = $this->extractor->extract($payload, '$.tags[*]');

    $this->assertSame(['php', 'drupal', 'webhook'], $result);
  }

  /**
   * Tests that null is returned when the JSONPath expression matches nothing.
   *
   * @covers ::extract
   */
  public function testExtractReturnsNullWhenPathNotFound(): void {
    $payload = ['name' => 'John'];

    $result = $this->extractor->extract($payload, '$.missing.path');

    $this->assertNull($result);
  }

  /**
   * Tests extraction of a specific array element by index.
   *
   * @covers ::extract
   */
  public function testExtractSpecificArrayElementByIndex(): void {
    $payload = [
      'items' => ['first', 'second', 'third'],
    ];

    $result = $this->extractor->extract($payload, '$.items[1]');

    $this->assertSame('second', $result);
  }

  /**
   * Tests that an array of values is returned for a pluck expression across objects.
   *
   * @covers ::extract
   */
  public function testExtractPluckFieldFromArrayOfObjects(): void {
    $payload = [
      'users' => [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
      ],
    ];

    $result = $this->extractor->extract($payload, '$.users[*].name');

    $this->assertSame(['Alice', 'Bob'], $result);
  }

}
