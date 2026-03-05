<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the interface for executing a chain of verification plugins.
 *
 * Implements AND logic: every plugin in the chain must pass for the
 * overall verification to succeed. An empty chain passes by default.
 */
interface VerificationChainInterface {
  /**
   * Runs all provided verification plugins against the request.
   *
   * Evaluates plugins in order and short-circuits on the first failure.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request to verify.
   * @param \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationInterface[] $plugins
   *   The verification plugin instances to run. May be empty.
   *
   * @return bool
   *   TRUE if all plugins pass (or the list is empty), FALSE otherwise.
   */
  public function verify(Request $request, array $plugins): bool;
}
