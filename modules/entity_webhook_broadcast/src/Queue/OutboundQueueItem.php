<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Queue;

/**
 * Value object representing a single item in the outbound webhook delivery queue.
 *
 * Instances are serialized to plain arrays for storage in Drupal's Queue API
 * and deserialized back via fromArray() during processing.
 */
final readonly class OutboundQueueItem {

  /**
   * Constructs an OutboundQueueItem.
   *
   * @param string $endpointId
   *   The OutboundEndpoint config entity ID.
   * @param string $subscriptionId
   *   The OutboundSubscription config entity ID.
   * @param string $entityTypeId
   *   The Drupal entity type ID of the source entity.
   * @param string $entityId
   *   The source entity ID.
   * @param string $event
   *   The CRUD event name: 'insert', 'update', or 'delete'.
   * @param array<string, mixed> $payload
   *   The pre-built webhook payload array.
   * @param string|null $deliveryLogId
   *   The OutboundDeliveryLog entity ID, or NULL before logging is implemented.
   * @param int $attempt
   *   The current delivery attempt number (1-based).
   * @param \DateTimeImmutable $dispatchedAt
   *   The timestamp when this item was first dispatched.
   */
  public function __construct(
    public readonly string $endpointId,
    public readonly string $subscriptionId,
    public readonly string $entityTypeId,
    public readonly string $entityId,
    public readonly string $event,
    public readonly array $payload,
    public readonly ?string $deliveryLogId,
    public readonly int $attempt,
    public readonly \DateTimeImmutable $dispatchedAt,
  ) {
  }

  /**
   * Creates an OutboundQueueItem from a plain array.
   *
   * @param array<string, mixed> $data
   *   Array with keys: endpoint_id, subscription_id, entity_type_id, entity_id,
   *   event, payload, delivery_log_id, attempt, dispatched_at.
   *
   * @return self
   *   A new OutboundQueueItem instance.
   */
  public static function fromArray(array $data): self {
    return new self(
      endpointId: (string) ($data['endpoint_id'] ?? ''),
      subscriptionId: (string) ($data['subscription_id'] ?? ''),
      entityTypeId: (string) ($data['entity_type_id'] ?? ''),
      entityId: (string) ($data['entity_id'] ?? ''),
      event: (string) ($data['event'] ?? ''),
      payload: (array) ($data['payload'] ?? []),
      deliveryLogId: isset($data['delivery_log_id']) ? (string) $data['delivery_log_id'] : NULL,
      attempt: (int) ($data['attempt'] ?? 1),
      dispatchedAt: self::parseDispatchedAt((string) ($data['dispatched_at'] ?? 'now')),
    );
  }

  /**
   * Serializes this item to a plain array for queue storage.
   *
   * @return array<string, mixed>
   *   Array with keys: endpoint_id, subscription_id, entity_type_id, entity_id,
   *   event, payload, delivery_log_id, attempt, dispatched_at.
   */
  public function toArray(): array {
    return [
      'endpoint_id' => $this->endpointId,
      'subscription_id' => $this->subscriptionId,
      'entity_type_id' => $this->entityTypeId,
      'entity_id' => $this->entityId,
      'event' => $this->event,
      'payload' => $this->payload,
      'delivery_log_id' => $this->deliveryLogId,
      'attempt' => $this->attempt,
      'dispatched_at' => $this->dispatchedAt->format(\DateTimeInterface::ATOM),
    ];
  }

  /**
   * Parses a datetime string into a DateTimeImmutable, falling back to now.
   *
   * @param string $value
   *   The datetime string to parse.
   *
   * @return \DateTimeImmutable
   *   The parsed datetime, or the current time if the value is malformed.
   */
  private static function parseDispatchedAt(string $value): \DateTimeImmutable {
    try {
      return new \DateTimeImmutable($value);
    }
    catch (\DateMalformedStringException) {
      return new \DateTimeImmutable();
    }
  }

}
