<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\FieldValueMutation;

use Drupal\entity_webhook\Plugin\FieldValueMutation\PriceCentsToDecimal;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the PriceCentsToDecimal plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\FieldValueMutation\PriceCentsToDecimal
 * @group entity_webhook
 */
class PriceCentsToDecimalTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\FieldValueMutation\PriceCentsToDecimal
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): PriceCentsToDecimal {
    return new PriceCentsToDecimal(
      $configuration,
      'price_cents_to_decimal',
      ['id' => 'price_cents_to_decimal', 'label' => 'Price: Cents to Decimal'],
    );
  }

  /**
   * Tests that mutate converts an integer cent value to a decimal.
   *
   * @covers ::mutate
   */
  public function testMutateConvertsIntegerCentsToDecimal(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(9.99, $plugin->mutate(999));
    $this->assertSame(1.00, $plugin->mutate(100));
    $this->assertSame(0.01, $plugin->mutate(1));
  }

  /**
   * Tests that mutate converts a string numeric cent value to a decimal.
   *
   * @covers ::mutate
   */
  public function testMutateConvertsStringNumericCentsToDecimal(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(9.99, $plugin->mutate('999'));
    $this->assertSame(10.00, $plugin->mutate('1000'));
  }

  /**
   * Tests that mutate converts zero cents to zero decimal.
   *
   * @covers ::mutate
   */
  public function testMutateConvertsZeroCentsToZeroDecimal(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(0.00, $plugin->mutate(0));
  }

  /**
   * Tests that mutate returns non-numeric values unchanged.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsNonNumericValueUnchanged(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('not-a-number', $plugin->mutate('not-a-number'));
    $this->assertNull($plugin->mutate(NULL));
    $this->assertSame(['array' => 'value'], $plugin->mutate(['array' => 'value']));
    $this->assertTrue($plugin->mutate(TRUE));
  }

  /**
   * Tests that mutate rounds the result to two decimal places.
   *
   * @covers ::mutate
   */
  public function testMutateRoundsResultToTwoDecimalPlaces(): void {
    $plugin = $this->createPlugin();

    // 1000/3 cents = 3.3333... should round to 3.33
    $result = $plugin->mutate(333);
    $this->assertSame(3.33, $result);
  }

  /**
   * Tests that mutate handles large cent values correctly.
   *
   * @covers ::mutate
   */
  public function testMutateHandlesLargeCentValues(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(999.99, $plugin->mutate(99999));
    $this->assertSame(10000.00, $plugin->mutate(1000000));
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('Price: Cents to Decimal', $plugin->label());
  }

}
