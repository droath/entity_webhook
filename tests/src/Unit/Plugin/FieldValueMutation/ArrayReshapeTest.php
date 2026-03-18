<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\FieldValueMutation;

use Drupal\entity_webhook\Plugin\FieldValueMutation\ArrayReshape;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the ArrayReshape plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\FieldValueMutation\ArrayReshape
 * @group entity_webhook
 */
class ArrayReshapeTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\FieldValueMutation\ArrayReshape
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): ArrayReshape {
    return new ArrayReshape(
      $configuration,
      'array_reshape',
      ['id' => 'array_reshape', 'label' => 'Array Reshape'],
    );
  }

  /**
   * Tests that mutate reshapes array items by mapping source keys to target keys.
   *
   * @covers ::mutate
   */
  public function testMutateReshapesArrayItemsByKeyMapping(): void {
    $plugin = $this->createPlugin([
      'mapping' => [
        'product_id' => 'id',
        'product_name' => 'title',
        'qty' => 'quantity',
      ],
    ]);

    $input = [
      ['id' => 1, 'title' => 'Widget', 'quantity' => 5, 'extra' => 'ignored'],
      ['id' => 2, 'title' => 'Gadget', 'quantity' => 3, 'extra' => 'ignored'],
    ];

    $result = $plugin->mutate($input);

    $this->assertSame([
      ['product_id' => 1, 'product_name' => 'Widget', 'qty' => 5],
      ['product_id' => 2, 'product_name' => 'Gadget', 'qty' => 3],
    ], $result);
  }

  /**
   * Tests that mutate returns the original value when it is not an array.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsNonArrayValueUnchanged(): void {
    $plugin = $this->createPlugin([
      'mapping' => ['target' => 'source'],
    ]);

    $this->assertSame('a string', $plugin->mutate('a string'));
    $this->assertSame(42, $plugin->mutate(42));
    $this->assertNull($plugin->mutate(NULL));
    $this->assertTrue($plugin->mutate(TRUE));
  }

  /**
   * Tests that mutate returns the original array when mapping is empty.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsOriginalArrayWhenMappingIsEmpty(): void {
    $plugin = $this->createPlugin(['mapping' => []]);

    $input = [['id' => 1, 'name' => 'test']];
    $result = $plugin->mutate($input);

    $this->assertSame($input, $result);
  }

  /**
   * Tests that mutate sets NULL for target keys whose source key is missing.
   *
   * @covers ::mutate
   */
  public function testMutateSetsNullForMissingSourceKeys(): void {
    $plugin = $this->createPlugin([
      'mapping' => [
        'product_id' => 'id',
        'sku' => 'sku_code',
      ],
    ]);

    $input = [['id' => 10]];

    $result = $plugin->mutate($input);

    $this->assertSame([['product_id' => 10, 'sku' => NULL]], $result);
  }

  /**
   * Tests that mutate replaces non-array items within the list with an empty array.
   *
   * @covers ::mutate
   */
  public function testMutateReplacesNonArrayItemsWithEmptyArray(): void {
    $plugin = $this->createPlugin([
      'mapping' => ['product_id' => 'id'],
    ]);

    $input = ['not an array item', 42];

    $result = $plugin->mutate($input);

    $this->assertSame([[], []], $result);
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
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('Array Reshape', $plugin->label());
  }

}
