<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\WebhookVerification;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\WebhookVerification;

/**
 * Verifies webhook requests by checking the client IP against a whitelist.
 *
 * The whitelist supports exact IP addresses and CIDR notation (e.g.
 * 192.168.1.0/24). One entry per line. All entries are checked; if any
 * matches, the request is allowed.
 */
#[WebhookVerification(
  id: 'domain_whitelist_verification',
  label: new TranslatableMarkup('IP/Domain Whitelist Verification'),
  description: new TranslatableMarkup('Allows requests only from configured IP addresses or CIDR ranges.'),
)]
class DomainWhitelistVerification extends WebhookVerificationBase {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'allowed_ips' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function verify(Request $request): bool {
    $allowedEntries = $this->parseAllowedIps();

    if (empty($allowedEntries)) {
      return FALSE;
    }

    $clientIp = $request->getClientIp() ?? '';

    if ($clientIp === '') {
      return FALSE;
    }

    foreach ($allowedEntries as $entry) {
      if ($this->ipMatchesEntry($clientIp, $entry)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['allowed_ips'] = [
      '#type' => 'textarea',
      '#title' => new TranslatableMarkup('Allowed IPs / CIDR ranges'),
      '#description' => new TranslatableMarkup('One entry per line. Supports exact IP addresses (e.g. 192.168.1.1) and CIDR notation (e.g. 192.168.1.0/24).'),
      '#default_value' => $this->configuration['allowed_ips'],
      '#rows' => 6,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['allowed_ips'] = $form_state->getValue('allowed_ips');
  }

  /**
   * Parses the allowed IPs textarea into an array of trimmed entries.
   *
   * @return string[]
   *   Non-empty, trimmed IP/CIDR strings.
   */
  private function parseAllowedIps(): array {
    $raw = $this->configuration['allowed_ips'];

    return array_values(array_filter(
      array_map('trim', explode("\n", (string) $raw)),
      static fn (string $entry) => $entry !== '',
    ));
  }

  /**
   * Tests whether the given client IP matches a whitelist entry.
   *
   * Supports exact IP comparison and CIDR range matching.
   *
   * @param string $clientIp
   *   The client IP address.
   * @param string $entry
   *   The whitelist entry (exact IP or CIDR).
   *
   * @return bool
   *   TRUE if the IP matches.
   */
  private function ipMatchesEntry(string $clientIp, string $entry): bool {
    if (!str_contains($entry, '/')) {
      return $clientIp === $entry;
    }

    return $this->ipMatchesCidr($clientIp, $entry);
  }

  /**
   * Checks if an IP address falls within a CIDR range.
   *
   * @param string $ip
   *   The IP address to check.
   * @param string $cidr
   *   The CIDR range (e.g. 192.168.1.0/24).
   *
   * @return bool
   *   TRUE if the IP is within the CIDR range.
   */
  private function ipMatchesCidr(string $ip, string $cidr): bool {
    [$range, $prefix] = explode('/', $cidr, 2);

    $prefixLength = (int) $prefix;
    $ipLong = ip2long($ip);
    $rangeLong = ip2long($range);

    if ($ipLong === FALSE || $rangeLong === FALSE) {
      return FALSE;
    }

    $mask = $prefixLength === 0 ? 0 : (~0 << (32 - $prefixLength));

    return ($ipLong & $mask) === ($rangeLong & $mask);
  }
}
