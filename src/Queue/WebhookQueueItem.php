<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Queue;

/**
 * Value object representing a single item in the webhook processing queue.
 *
 * Instances are serialized to plain arrays for storage in Drupal's Queue API
 * and deserialized back via fromArray() during processing.
 */
final readonly class WebhookQueueItem {

  /**
   * Constructs a WebhookQueueItem.
   *
   * @param string $endpointId
   *   The WebhookEndpoint config entity ID that received the request.
   * @param string $sourceType
   *   The WebhookSourceType config entity ID identifying the payload schema.
   * @param array<string, mixed> $payload
   *   The decoded JSON payload body.
   * @param \DateTimeImmutable $receivedAt
   *   The timestamp when the webhook was received.
   * @param string $source
   *   The origin of the item: 'webhook' or 'polling'.
   */
  public function __construct(
    public string $endpointId,
    public string $sourceType,
    public array $payload,
    public \DateTimeImmutable $receivedAt,
    public string $source,
  ) {}

  /**
   * Creates a WebhookQueueItem from a plain array.
   *
   * @param array<string, mixed> $data
   *   Array with keys: endpoint_id, source_type, payload, received_at, source.
   *
   * @return self
   *   A new WebhookQueueItem instance.
   *
   * @throws \DateMalformedStringException
   */
  public static function fromArray(array $data): self {
    return new self(
      endpointId: (string) ($data['endpoint_id'] ?? ''),
      sourceType: (string) ($data['source_type'] ?? ''),
      payload: (array) ($data['payload'] ?? []),
      receivedAt: new \DateTimeImmutable((string) ($data['received_at'] ?? 'now')),
      source: (string) ($data['source'] ?? 'webhook'),
    );
  }

  /**
   * Serializes this item to a plain array for queue storage.
   *
   * @return array<string, mixed>
   *   Array with keys: endpoint_id, source_type, payload, received_at, source.
   */
  public function toArray(): array {
    return [
      'endpoint_id' => $this->endpointId,
      'source_type' => $this->sourceType,
      'payload' => $this->payload,
      'received_at' => $this->receivedAt->format(\DateTimeInterface::ATOM),
      'source' => $this->source,
    ];
  }

}
