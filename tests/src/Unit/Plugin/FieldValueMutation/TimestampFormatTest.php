<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\FieldValueMutation;

use Drupal\entity_webhook\Plugin\FieldValueMutation\TimestampFormat;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the TimestampFormat plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\FieldValueMutation\TimestampFormat
 * @group entity_webhook
 */
class TimestampFormatTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\FieldValueMutation\TimestampFormat
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): TimestampFormat {
    return new TimestampFormat(
      $configuration,
      'timestamp_format',
      ['id' => 'timestamp_format', 'label' => 'Timestamp Format'],
    );
  }

  /**
   * Tests that mutate converts an ISO 8601 date string to a Unix timestamp.
   *
   * @covers ::mutate
   */
  public function testMutateConvertsIso8601DateStringToTimestamp(): void {
    $plugin = $this->createPlugin();

    $result = $plugin->mutate('2024-01-15T12:30:00Z');

    $this->assertIsInt($result);
    $this->assertSame(strtotime('2024-01-15T12:30:00Z'), $result);
  }

  /**
   * Tests that mutate converts a plain date string to a Unix timestamp.
   *
   * @covers ::mutate
   */
  public function testMutateConvertsPlainDateStringToTimestamp(): void {
    $plugin = $this->createPlugin();

    $result = $plugin->mutate('2024-06-01');

    $this->assertIsInt($result);
    $this->assertSame(strtotime('2024-06-01'), $result);
  }

  /**
   * Tests that mutate returns NULL for an empty string.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsNullForEmptyString(): void {
    $plugin = $this->createPlugin();

    $this->assertNull($plugin->mutate(''));
  }

  /**
   * Tests that mutate returns NULL for a non-string value.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsNullForNonStringValue(): void {
    $plugin = $this->createPlugin();

    $this->assertNull($plugin->mutate(NULL));
    $this->assertNull($plugin->mutate(42));
    $this->assertNull($plugin->mutate(['date' => '2024-01-01']));
    $this->assertNull($plugin->mutate(TRUE));
  }

  /**
   * Tests that mutate returns NULL for an unparseable date string.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsNullForUnparseableDateString(): void {
    $plugin = $this->createPlugin();

    $this->assertNull($plugin->mutate('not-a-date-at-all'));
  }

  /**
   * Tests that mutate handles a date string with timezone offset.
   *
   * @covers ::mutate
   */
  public function testMutateHandlesDateStringWithTimezoneOffset(): void {
    $plugin = $this->createPlugin();

    $result = $plugin->mutate('2024-03-15T10:00:00+05:00');

    $this->assertIsInt($result);
    $this->assertSame(strtotime('2024-03-15T10:00:00+05:00'), $result);
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('Timestamp Format', $plugin->label());
  }

}
