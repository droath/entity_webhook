<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;

/**
 * Defines the interface for delivering webhook payloads to subscriber endpoints.
 *
 * Implementations send the payload as an HTTP POST, optionally signing it with
 * HMAC, and return a structured result regardless of success or failure.
 */
interface DeliveryServiceInterface {

  /**
   * Delivers a webhook payload to the subscription's configured URL.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The subscription defining the destination URL, signing config, etc.
   * @param array<string, mixed> $payload
   *   The structured payload to deliver as JSON.
   *
   * @return \Drupal\entity_webhook_broadcast\Service\DeliveryResult
   *   The delivery result indicating success or failure details.
   */
  public function deliver(OutboundSubscriptionInterface $subscription, array $payload): DeliveryResult;

}
