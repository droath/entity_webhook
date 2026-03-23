<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the interface for OutboundDeliveryLog content entities.
 *
 * An OutboundDeliveryLog records every webhook delivery attempt, storing
 * the outcome, HTTP status, response body, and retry scheduling information.
 * It serves as both an audit trail and the data source for cron-based retry.
 */
interface OutboundDeliveryLogInterface extends ContentEntityInterface {

  /**
   * Returns the OutboundSubscription config entity ID.
   *
   * @return string
   *   The subscription machine name.
   */
  public function getSubscriptionId(): string;

  /**
   * Returns the source entity type ID.
   *
   * @return string
   *   The Drupal entity type machine name.
   */
  public function getSourceEntityType(): string;

  /**
   * Returns the source entity ID.
   *
   * @return string
   *   The source entity ID as a string.
   */
  public function getSourceEntityId(): string;

  /**
   * Returns the CRUD event name.
   *
   * @return string
   *   One of 'insert', 'update', or 'delete'.
   */
  public function getEvent(): string;

  /**
   * Returns the SHA-256 hash of the serialized payload.
   *
   * @return string
   *   The hex-encoded payload hash.
   */
  public function getPayloadHash(): string;

  /**
   * Returns the stored webhook payload for retry re-enqueue.
   *
   * @return array<string, mixed>
   *   The decoded payload array, or empty array if not stored.
   */
  public function getPayload(): array;

  /**
   * Stores the webhook payload for retry re-enqueue.
   *
   * @param array<string, mixed> $payload
   *   The payload to serialize and store.
   *
   * @return $this
   */
  public function setPayload(array $payload): static;

  /**
   * Returns the current delivery attempt number.
   *
   * @return int
   *   The 1-based attempt number.
   */
  public function getAttempt(): int;

  /**
   * Returns the maximum number of delivery attempts for this log entry.
   *
   * @return int
   *   The maximum attempts, copied from the subscription at dispatch time.
   */
  public function getMaxAttempts(): int;

  /**
   * Returns the current delivery status.
   *
   * @return string
   *   One of 'pending', 'success', 'failed', or 'abandoned'.
   */
  public function getStatus(): string;

  /**
   * Returns the HTTP response status code from the last attempt.
   *
   * @return int|null
   *   The HTTP status code, or NULL if no HTTP response was received.
   */
  public function getHttpStatus(): ?int;

  /**
   * Returns the truncated response body from the last attempt.
   *
   * @return string|null
   *   The first 1KB of the response body, or NULL if unavailable.
   */
  public function getResponseBody(): ?string;

  /**
   * Returns the timestamp for the next retry attempt.
   *
   * @return int|null
   *   Unix timestamp when the item should be retried, or NULL if not scheduled.
   */
  public function getNextRetryAt(): ?int;

  /**
   * Returns the timestamp when this log entry was first created.
   *
   * @return int
   *   Unix timestamp of the initial dispatch.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the delivery status.
   *
   * @param string $status
   *   One of 'pending', 'success', 'failed', or 'abandoned'.
   *
   * @return $this
   */
  public function setStatus(string $status): static;

  /**
   * Sets the HTTP response status code.
   *
   * @param int|null $httpStatus
   *   The HTTP status code, or NULL if no response was received.
   *
   * @return $this
   */
  public function setHttpStatus(?int $httpStatus): static;

  /**
   * Sets the truncated response body.
   *
   * @param string|null $responseBody
   *   The response body, or NULL if unavailable.
   *
   * @return $this
   */
  public function setResponseBody(?string $responseBody): static;

  /**
   * Sets the next retry timestamp.
   *
   * @param int|null $timestamp
   *   Unix timestamp when to retry, or NULL to clear.
   *
   * @return $this
   */
  public function setNextRetryAt(?int $timestamp): static;

  /**
   * Increments the attempt counter by one.
   *
   * @return $this
   */
  public function incrementAttempt(): static;

}
