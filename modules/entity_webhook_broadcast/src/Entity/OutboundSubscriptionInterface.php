<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for OutboundSubscription config entities.
 *
 * An OutboundSubscription belongs to a parent OutboundEndpoint and defines
 * where to deliver webhook payloads, how to sign them, and retry policy.
 */
interface OutboundSubscriptionInterface extends ConfigEntityInterface {

  /**
   * Returns the parent OutboundEndpoint machine name.
   *
   * @return string
   *   The endpoint ID.
   */
  public function getEndpointId(): string;

  /**
   * Returns the parent OutboundEndpoint entity.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null
   *   The endpoint entity, or NULL if not found.
   */
  public function getEndpoint(): ?OutboundEndpointInterface;

  /**
   * Returns the destination webhook URL.
   *
   * @return string
   *   The URL to POST webhook payloads to.
   */
  public function getUrl(): string;

  /**
   * Returns the shared HMAC secret.
   *
   * @return string|null
   *   The secret, or NULL if signing is disabled.
   */
  public function getSecret(): ?string;

  /**
   * Returns the HMAC signing algorithm.
   *
   * @return string
   *   One of 'sha256', 'sha1', or 'none'.
   */
  public function getSigningAlgorithm(): string;

  /**
   * Returns the maximum number of delivery attempts.
   *
   * @return int
   *   The retry limit.
   */
  public function getRetryMaxAttempts(): int;

  /**
   * Returns the base delay in seconds for exponential backoff.
   *
   * @return int
   *   Base delay seconds.
   */
  public function getRetryBaseDelay(): int;

  /**
   * Returns whether this subscription is active.
   *
   * @return bool
   *   TRUE if the subscription will receive dispatched webhooks.
   */
  public function isActive(): bool;

}
