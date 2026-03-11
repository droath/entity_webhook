<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Service;

use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationInterface;
use Drupal\entity_webhook\Service\VerificationChain;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for the VerificationChain service.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Service\VerificationChain
 * @group entity_webhook
 */
class VerificationChainTest extends UnitTestCase {

  /**
   * Creates a mock verification plugin that returns the given result.
   *
   * @param bool $result
   *   The value verify() should return.
   *
   * @return \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationInterface
   *   The mock plugin.
   */
  private function createMockPlugin(bool $result): WebhookVerificationInterface {
    $plugin = $this->createMock(WebhookVerificationInterface::class);
    $plugin->method('verify')->willReturn($result);

    return $plugin;
  }

  /**
   * Tests that an empty plugin list passes (no verification configured).
   *
   * @covers ::verify
   */
  public function testEmptyPluginListPassesVerification(): void {
    $chain = new VerificationChain();
    $request = Request::create('/webhook/test/source', 'POST');

    $this->assertTrue($chain->verify($request, []));
  }

  /**
   * Tests that all-passing plugins result in overall pass (AND logic).
   *
   * @covers ::verify
   */
  public function testAllPassingPluginsResultInPass(): void {
    $chain = new VerificationChain();
    $request = Request::create('/webhook/test/source', 'POST');
    $plugins = [
      $this->createMockPlugin(TRUE),
      $this->createMockPlugin(TRUE),
    ];

    $this->assertTrue($chain->verify($request, $plugins));
  }

  /**
   * Tests that one failing plugin causes overall failure (AND logic).
   *
   * @covers ::verify
   */
  public function testOneFailingPluginCausesFailure(): void {
    $chain = new VerificationChain();
    $request = Request::create('/webhook/test/source', 'POST');
    $plugins = [
      $this->createMockPlugin(TRUE),
      $this->createMockPlugin(FALSE),
      $this->createMockPlugin(TRUE),
    ];

    $this->assertFalse($chain->verify($request, $plugins));
  }

  /**
   * Tests that chain stops evaluating after the first failure (short-circuit).
   *
   * @covers ::verify
   */
  public function testChainShortCircuitsOnFirstFailure(): void {
    $chain = new VerificationChain();
    $request = Request::create('/webhook/test/source', 'POST');

    $firstPlugin = $this->createMock(WebhookVerificationInterface::class);
    $firstPlugin->method('verify')->willReturn(FALSE);

    $secondPlugin = $this->createMock(WebhookVerificationInterface::class);
    $secondPlugin->expects($this->never())->method('verify');

    $this->assertFalse($chain->verify($request, [$firstPlugin, $secondPlugin]));
  }

  /**
   * Tests that a non-plugin object throws InvalidArgumentException.
   *
   * Passing an object that does not implement WebhookVerificationInterface is a
   * programmer error and must not be silently ignored.
   *
   * @covers ::verify
   */
  public function testInvalidPluginThrowsInvalidArgumentException(): void {
    $chain = new VerificationChain();
    $request = Request::create('/webhook/test/source', 'POST');
    $invalidPlugin = new \stdClass();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('stdClass');

    // @phpstan-ignore argument.type (intentionally passing invalid type to test the guard)
    $chain->verify($request, [$invalidPlugin]);
  }

}
