<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\FieldValueMutation;

use Drupal\entity_webhook\Plugin\FieldValueMutation\JsonEncode;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the JsonEncode plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\FieldValueMutation\JsonEncode
 * @group entity_webhook
 */
class JsonEncodeTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\FieldValueMutation\JsonEncode
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): JsonEncode {
    return new JsonEncode(
      $configuration,
      'json_encode',
      ['id' => 'json_encode', 'label' => 'JSON Encode'],
    );
  }

  /**
   * Tests that mutate encodes an array as a JSON string.
   *
   * @covers ::mutate
   */
  public function testMutateEncodesArrayAsJsonString(): void {
    $plugin = $this->createPlugin();

    $result = $plugin->mutate(['key' => 'value', 'num' => 1]);

    $this->assertSame('{"key":"value","num":1}', $result);
  }

  /**
   * Tests that mutate returns a string value unchanged without re-encoding.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsStringValueUnchanged(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('already a string', $plugin->mutate('already a string'));
    $this->assertSame('{"pre":"encoded"}', $plugin->mutate('{"pre":"encoded"}'));
  }

  /**
   * Tests that mutate encodes an integer as a JSON string.
   *
   * @covers ::mutate
   */
  public function testMutateEncodesIntegerAsJsonString(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('42', $plugin->mutate(42));
  }

  /**
   * Tests that mutate encodes NULL as a JSON null string.
   *
   * @covers ::mutate
   */
  public function testMutateEncodesNullAsJsonNullString(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('null', $plugin->mutate(NULL));
  }

  /**
   * Tests that mutate encodes a boolean as a JSON string.
   *
   * @covers ::mutate
   */
  public function testMutateEncodesBooleanAsJsonString(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('true', $plugin->mutate(TRUE));
    $this->assertSame('false', $plugin->mutate(FALSE));
  }

  /**
   * Tests that mutate encodes a nested array as a JSON string.
   *
   * @covers ::mutate
   */
  public function testMutateEncodesNestedArrayAsJsonString(): void {
    $plugin = $this->createPlugin();

    $input = ['items' => [['id' => 1], ['id' => 2]]];
    $result = $plugin->mutate($input);

    $this->assertSame('{"items":[{"id":1},{"id":2}]}', $result);
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('JSON Encode', $plugin->label());
  }

}
