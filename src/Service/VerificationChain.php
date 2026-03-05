<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Symfony\Component\HttpFoundation\Request;
use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationInterface;

/**
 * Runs a list of verification plugins with AND logic.
 *
 * All configured plugins must pass for the chain to succeed.
 * Evaluation stops immediately on the first failure (short-circuit).
 */
class VerificationChain implements VerificationChainInterface {
  /**
   * {@inheritdoc}
   */
  public function verify(Request $request, array $plugins): bool {
    foreach ($plugins as $plugin) {
      if (!$plugin instanceof WebhookVerificationInterface) {
        continue;
      }

      if (!$plugin->verify($request)) {
        return FALSE;
      }
    }

    return TRUE;
  }
}
