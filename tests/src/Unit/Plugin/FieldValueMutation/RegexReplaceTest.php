<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\FieldValueMutation;

use Drupal\entity_webhook\Plugin\FieldValueMutation\RegexReplace;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the RegexReplace plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\FieldValueMutation\RegexReplace
 * @group entity_webhook
 */
class RegexReplaceTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\FieldValueMutation\RegexReplace
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): RegexReplace {
    return new RegexReplace(
      $configuration,
      'regex_replace',
      ['id' => 'regex_replace', 'label' => 'Regex Replace'],
    );
  }

  /**
   * Tests that mutate replaces matches using the configured regex pattern.
   *
   * @covers ::mutate
   */
  public function testMutateReplacesMatchesUsingRegexPattern(): void {
    $plugin = $this->createPlugin([
      'pattern' => '/\d+/',
      'replacement' => 'NUM',
    ]);

    $result = $plugin->mutate('foo 123 bar 456');

    $this->assertSame('foo NUM bar NUM', $result);
  }

  /**
   * Tests that mutate returns a non-string value unchanged.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsNonStringValueUnchanged(): void {
    $plugin = $this->createPlugin([
      'pattern' => '/\d+/',
      'replacement' => 'NUM',
    ]);

    $this->assertSame(42, $plugin->mutate(42));
    $this->assertNull($plugin->mutate(NULL));
    $this->assertTrue($plugin->mutate(TRUE));
  }

  /**
   * Tests that mutate returns the original value when the pattern is invalid.
   *
   * An invalid pattern (missing delimiters) causes preg_replace to return NULL,
   * and the plugin must fall back to returning the original value unchanged.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsOriginalValueOnInvalidPattern(): void {
    $plugin = $this->createPlugin([
      'pattern' => 'not_a_valid_pattern',
      'replacement' => 'replaced',
    ]);

    $original = 'hello world';
    $result = $plugin->mutate($original);

    $this->assertSame($original, $result);
  }

  /**
   * Tests that mutate supports capture group backreferences in replacement.
   *
   * @covers ::mutate
   */
  public function testMutateSupportsCaptureGroupsInReplacement(): void {
    $plugin = $this->createPlugin([
      'pattern' => '/(\w+)\s+(\w+)/',
      'replacement' => '$2 $1',
    ]);

    $result = $plugin->mutate('hello world');

    $this->assertSame('world hello', $result);
  }

  /**
   * Tests that mutate returns original string when pattern does not match.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsOriginalStringWhenPatternDoesNotMatch(): void {
    $plugin = $this->createPlugin([
      'pattern' => '/\d+/',
      'replacement' => 'NUM',
    ]);

    $result = $plugin->mutate('no numbers here');

    $this->assertSame('no numbers here', $result);
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    $plugin = $this->createPlugin();

    $this->assertSame('Regex Replace', $plugin->label());
  }

}
