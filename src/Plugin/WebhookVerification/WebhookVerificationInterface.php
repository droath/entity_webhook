<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\WebhookVerification;

use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines the interface for webhook verification plugins.
 *
 * Verification plugins authenticate incoming webhook requests. Multiple
 * plugins can be configured per source type and are evaluated with AND logic:
 * all configured plugins must pass for verification to succeed.
 */
interface WebhookVerificationInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {
  /**
   * Verifies that the incoming request is authentic.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request to verify.
   *
   * @return bool
   *   TRUE if the request passes verification, FALSE otherwise.
   */
  public function verify(Request $request): bool;

  /**
   * Returns the human-readable label for this verification plugin.
   *
   * @return string
   *   The plugin label.
   */
  public function label(): string;
}
