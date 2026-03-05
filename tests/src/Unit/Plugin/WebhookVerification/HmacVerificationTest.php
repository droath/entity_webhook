<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\WebhookVerification;

use Drupal\entity_webhook\Plugin\WebhookVerification\HmacVerification;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for the HmacVerification plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\WebhookVerification\HmacVerification
 * @group entity_webhook
 */
class HmacVerificationTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\WebhookVerification\HmacVerification
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): HmacVerification {
    return new HmacVerification(
      $configuration,
      'hmac_verification',
      ['id' => 'hmac_verification', 'label' => 'HMAC Verification'],
    );
  }

  /**
   * Builds a request with the given body and HMAC signature header.
   *
   * @param string $body
   *   The raw request body.
   * @param string $headerName
   *   The signature header name.
   * @param string $signature
   *   The raw signature value to include in the header.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request with signature header.
   */
  private function buildRequest(string $body, string $headerName, string $signature): Request {
    $request = Request::create('/webhook/test/source', 'POST', [], [], [], [], $body);
    $request->headers->set($headerName, $signature);

    return $request;
  }

  /**
   * Computes the expected sha256 HMAC signature for the given body and secret.
   *
   * @param string $body
   *   The request body.
   * @param string $secret
   *   The shared secret.
   *
   * @return string
   *   The "sha256=" prefixed hex-encoded HMAC signature.
   */
  private function computeSignature(string $body, string $secret): string {
    return 'sha256=' . hash_hmac('sha256', $body, $secret);
  }

  /**
   * Tests that a valid HMAC signature passes verification.
   *
   * @covers ::verify
   */
  public function testValidSignaturePassesVerification(): void {
    $secret = 'my-secret-key';
    $body = '{"event":"created"}';
    $signature = $this->computeSignature($body, $secret);

    $plugin = $this->createPlugin([
      'secret' => $secret,
      'header' => 'X-Hub-Signature-256',
    ]);
    $request = $this->buildRequest($body, 'X-Hub-Signature-256', $signature);

    $this->assertTrue($plugin->verify($request));
  }

  /**
   * Tests that a tampered body fails verification.
   *
   * @covers ::verify
   */
  public function testTamperedBodyFailsVerification(): void {
    $secret = 'my-secret-key';
    $originalBody = '{"event":"created"}';
    $signature = $this->computeSignature($originalBody, $secret);

    $plugin = $this->createPlugin([
      'secret' => $secret,
      'header' => 'X-Hub-Signature-256',
    ]);
    $request = $this->buildRequest('{"event":"tampered"}', 'X-Hub-Signature-256', $signature);

    $this->assertFalse($plugin->verify($request));
  }

  /**
   * Tests that a missing signature header fails verification.
   *
   * @covers ::verify
   */
  public function testMissingSignatureHeaderFailsVerification(): void {
    $plugin = $this->createPlugin([
      'secret' => 'my-secret-key',
      'header' => 'X-Hub-Signature-256',
    ]);
    $request = Request::create('/webhook/test/source', 'POST', [], [], [], [], '{"event":"created"}');

    $this->assertFalse($plugin->verify($request));
  }

  /**
   * Tests that a wrong secret fails verification.
   *
   * @covers ::verify
   */
  public function testWrongSecretFailsVerification(): void {
    $body = '{"event":"created"}';
    $signature = $this->computeSignature($body, 'correct-secret');

    $plugin = $this->createPlugin([
      'secret' => 'wrong-secret',
      'header' => 'X-Hub-Signature-256',
    ]);
    $request = $this->buildRequest($body, 'X-Hub-Signature-256', $signature);

    $this->assertFalse($plugin->verify($request));
  }

  /**
   * Tests that a custom header name is used for signature lookup.
   *
   * @covers ::verify
   */
  public function testCustomHeaderNameIsRespected(): void {
    $secret = 'shopify-secret';
    $body = '{"order_id":123}';
    $signature = $this->computeSignature($body, $secret);

    $plugin = $this->createPlugin([
      'secret' => $secret,
      'header' => 'X-Shopify-Hmac-SHA256',
    ]);
    $request = $this->buildRequest($body, 'X-Shopify-Hmac-SHA256', $signature);

    $this->assertTrue($plugin->verify($request));
  }

}
