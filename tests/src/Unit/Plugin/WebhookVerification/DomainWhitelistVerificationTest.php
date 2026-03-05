<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\WebhookVerification;

use Drupal\entity_webhook\Plugin\WebhookVerification\DomainWhitelistVerification;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for the DomainWhitelistVerification plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\WebhookVerification\DomainWhitelistVerification
 * @group entity_webhook
 */
class DomainWhitelistVerificationTest extends UnitTestCase {

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook\Plugin\WebhookVerification\DomainWhitelistVerification
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): DomainWhitelistVerification {
    return new DomainWhitelistVerification(
      $configuration,
      'domain_whitelist_verification',
      ['id' => 'domain_whitelist_verification', 'label' => 'Domain Whitelist Verification'],
    );
  }

  /**
   * Builds a request with the given client IP address.
   *
   * @param string $clientIp
   *   The client IP to simulate.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request with REMOTE_ADDR set.
   */
  private function buildRequestWithIp(string $clientIp): Request {
    return Request::create(
      '/webhook/test/source',
      'POST',
      [],
      [],
      [],
      ['REMOTE_ADDR' => $clientIp],
      '{}',
    );
  }

  /**
   * Tests that an exact IP match passes verification.
   *
   * @covers ::verify
   */
  public function testExactIpMatchPassesVerification(): void {
    $plugin = $this->createPlugin(['allowed_ips' => "192.168.1.1\n10.0.0.1"]);
    $request = $this->buildRequestWithIp('192.168.1.1');

    $this->assertTrue($plugin->verify($request));
  }

  /**
   * Tests that an IP not in the whitelist fails verification.
   *
   * @covers ::verify
   */
  public function testIpNotInWhitelistFailsVerification(): void {
    $plugin = $this->createPlugin(['allowed_ips' => "192.168.1.1\n10.0.0.1"]);
    $request = $this->buildRequestWithIp('172.16.0.1');

    $this->assertFalse($plugin->verify($request));
  }

  /**
   * Tests that CIDR notation allows IPs within the range.
   *
   * @covers ::verify
   */
  public function testCidrNotationAllowsIpsInRange(): void {
    $plugin = $this->createPlugin(['allowed_ips' => '192.168.1.0/24']);
    $request = $this->buildRequestWithIp('192.168.1.100');

    $this->assertTrue($plugin->verify($request));
  }

  /**
   * Tests that an IP outside a CIDR range fails verification.
   *
   * @covers ::verify
   */
  public function testIpOutsideCidrRangeFailsVerification(): void {
    $plugin = $this->createPlugin(['allowed_ips' => '192.168.1.0/24']);
    $request = $this->buildRequestWithIp('192.168.2.1');

    $this->assertFalse($plugin->verify($request));
  }

  /**
   * Tests that an empty whitelist fails all requests.
   *
   * @covers ::verify
   */
  public function testEmptyWhitelistFailsAllRequests(): void {
    $plugin = $this->createPlugin(['allowed_ips' => '']);
    $request = $this->buildRequestWithIp('192.168.1.1');

    $this->assertFalse($plugin->verify($request));
  }

}
