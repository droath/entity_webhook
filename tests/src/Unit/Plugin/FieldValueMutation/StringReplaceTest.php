<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\FieldValueMutation;

use Drupal\entity_webhook\Plugin\FieldValueMutation\StringReplace;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the StringReplace plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\FieldValueMutation\StringReplace
 * @group entity_webhook
 */
class StringReplaceTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\FieldValueMutation\StringReplace
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): StringReplace {
    return new StringReplace(
      $configuration,
      'string_replace',
      ['id' => 'string_replace', 'label' => 'String Replace'],
    );
  }

  /**
   * Tests that mutate replaces a substring within a string value.
   *
   * @covers ::mutate
   */
  public function testMutateReplacesSubstringInStringValue(): void {
    $plugin = $this->createPlugin([
      'search' => 'foo',
      'replace' => 'bar',
    ]);

    $result = $plugin->mutate('foo baz foo');

    $this->assertSame('bar baz bar', $result);
  }

  /**
   * Tests that mutate returns a non-string value unchanged.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsNonStringValueUnchanged(): void {
    $plugin = $this->createPlugin([
      'search' => 'foo',
      'replace' => 'bar',
    ]);

    $this->assertSame(42, $plugin->mutate(42));
    $this->assertSame(3.14, $plugin->mutate(3.14));
    $this->assertNull($plugin->mutate(NULL));
    $this->assertTrue($plugin->mutate(TRUE));
  }

  /**
   * Tests that mutate returns the original string when search is not found.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsOriginalStringWhenSearchNotFound(): void {
    $plugin = $this->createPlugin([
      'search' => 'notpresent',
      'replace' => 'bar',
    ]);

    $result = $plugin->mutate('hello world');

    $this->assertSame('hello world', $result);
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('String Replace', $plugin->label());
  }

}
