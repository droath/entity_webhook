<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\WebhookVerification;

use Drupal\entity_webhook\Plugin\WebhookVerification\ApiKeyVerification;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for the ApiKeyVerification plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\WebhookVerification\ApiKeyVerification
 * @group entity_webhook
 */
class ApiKeyVerificationTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\WebhookVerification\ApiKeyVerification
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): ApiKeyVerification {
    return new ApiKeyVerification(
      $configuration,
      'api_key_verification',
      ['id' => 'api_key_verification', 'label' => 'API Key Verification'],
    );
  }

  /**
   * Tests that a correct API key in the header passes verification.
   *
   * @covers ::verify
   */
  public function testCorrectApiKeyInHeaderPassesVerification(): void {
    $plugin = $this->createPlugin([
      'api_key' => 'secret-api-key',
      'source' => 'header',
      'header_name' => 'X-API-Key',
      'query_param' => '',
    ]);
    $request = Request::create('/webhook/test/source', 'POST');
    $request->headers->set('X-API-Key', 'secret-api-key');

    $this->assertTrue($plugin->verify($request));
  }

  /**
   * Tests that a wrong API key in the header fails verification.
   *
   * @covers ::verify
   */
  public function testWrongApiKeyInHeaderFailsVerification(): void {
    $plugin = $this->createPlugin([
      'api_key' => 'secret-api-key',
      'source' => 'header',
      'header_name' => 'X-API-Key',
      'query_param' => '',
    ]);
    $request = Request::create('/webhook/test/source', 'POST');
    $request->headers->set('X-API-Key', 'wrong-key');

    $this->assertFalse($plugin->verify($request));
  }

  /**
   * Tests that a correct API key in a query parameter passes verification.
   *
   * @covers ::verify
   */
  public function testCorrectApiKeyInQueryParamPassesVerification(): void {
    $plugin = $this->createPlugin([
      'api_key' => 'secret-api-key',
      'source' => 'query',
      'header_name' => '',
      'query_param' => 'token',
    ]);
    $request = Request::create('/webhook/test/source?token=secret-api-key', 'POST');

    $this->assertTrue($plugin->verify($request));
  }

  /**
   * Tests that a missing API key in the configured location fails verification.
   *
   * @covers ::verify
   */
  public function testMissingApiKeyFailsVerification(): void {
    $plugin = $this->createPlugin([
      'api_key' => 'secret-api-key',
      'source' => 'header',
      'header_name' => 'X-API-Key',
      'query_param' => '',
    ]);
    $request = Request::create('/webhook/test/source', 'POST');

    $this->assertFalse($plugin->verify($request));
  }

  /**
   * Tests that an empty configured API key fails all requests.
   *
   * An empty key must not match an empty header to prevent accidental open access.
   *
   * @covers ::verify
   */
  public function testEmptyConfiguredApiKeyFailsAllRequests(): void {
    $plugin = $this->createPlugin([
      'api_key' => '',
      'source' => 'header',
      'header_name' => 'X-API-Key',
      'query_param' => '',
    ]);
    $request = Request::create('/webhook/test/source', 'POST');

    $this->assertFalse($plugin->verify($request));
  }

}
