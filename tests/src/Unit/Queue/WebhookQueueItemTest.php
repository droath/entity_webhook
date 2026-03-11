<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Queue;

use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the WebhookQueueItem value object.
 *
 * @group entity_webhook
 * @coversDefaultClass \Drupal\entity_webhook\Queue\WebhookQueueItem
 */
class WebhookQueueItemTest extends UnitTestCase {

  /**
   * Tests that a WebhookQueueItem can be constructed with required properties.
   */
  public function testConstructWithRequiredProperties(): void {
    $receivedAt = new \DateTimeImmutable('2026-03-04T12:00:00Z');
    $payload = ['id' => 42, 'name' => 'Test Product'];

    $item = new WebhookQueueItem(
      endpointId: 'my_endpoint',
      sourceType: 'shopify_order',
      payload: $payload,
      receivedAt: $receivedAt,
      source: 'webhook',
    );

    $this->assertSame('my_endpoint', $item->endpointId);
    $this->assertSame('shopify_order', $item->sourceType);
    $this->assertSame($payload, $item->payload);
    $this->assertSame($receivedAt, $item->receivedAt);
    $this->assertSame('webhook', $item->source);
  }

  /**
   * Tests that a WebhookQueueItem can be created from an array.
   */
  public function testFromArrayCreatesItemCorrectly(): void {
    $data = [
      'endpoint_id' => 'my_endpoint',
      'source_type' => 'shopify_order',
      'payload' => ['id' => 42],
      'received_at' => '2026-03-04T12:00:00+00:00',
      'source' => 'polling',
    ];

    $item = WebhookQueueItem::fromArray($data);

    $this->assertSame('my_endpoint', $item->endpointId);
    $this->assertSame('shopify_order', $item->sourceType);
    $this->assertSame(['id' => 42], $item->payload);
    $this->assertSame('polling', $item->source);
    $this->assertNotEmpty($item->receivedAt->format('c'));
  }

  /**
   * Tests that toArray serializes all properties for queue storage.
   */
  public function testToArraySerializesAllProperties(): void {
    $receivedAt = new \DateTimeImmutable('2026-03-04T12:00:00+00:00');
    $item = new WebhookQueueItem(
      endpointId: 'ep1',
      sourceType: 'src1',
      payload: ['key' => 'value'],
      receivedAt: $receivedAt,
      source: 'webhook',
    );

    $array = $item->toArray();

    $this->assertSame('ep1', $array['endpoint_id']);
    $this->assertSame('src1', $array['source_type']);
    $this->assertSame(['key' => 'value'], $array['payload']);
    $this->assertSame('webhook', $array['source']);
    $this->assertIsString($array['received_at']);
  }

  /**
   * Tests that a malformed received_at value falls back to the current time.
   */
  public function testFromArrayWithMalformedDateFallsBackToCurrentTime(): void {
    $before = new \DateTimeImmutable();

    $item = WebhookQueueItem::fromArray([
      'endpoint_id' => 'ep',
      'source_type' => 'st',
      'payload' => [],
      'received_at' => 'not-a-valid-date!!@@##',
      'source' => 'webhook',
    ]);

    $after = new \DateTimeImmutable();

    $this->assertGreaterThanOrEqual(
      $before->getTimestamp(),
      $item->receivedAt->getTimestamp(),
    );
    $this->assertLessThanOrEqual(
      $after->getTimestamp(),
      $item->receivedAt->getTimestamp(),
    );
  }

  /**
   * Tests round-trip serialization: toArray -> fromArray preserves all data.
   */
  public function testRoundTripSerializationPreservesData(): void {
    $original = new WebhookQueueItem(
      endpointId: 'endpoint_abc',
      sourceType: 'my_source',
      payload: ['id' => 99, 'tags' => ['a', 'b']],
      receivedAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      source: 'polling',
    );

    $restored = WebhookQueueItem::fromArray($original->toArray());

    $this->assertSame($original->endpointId, $restored->endpointId);
    $this->assertSame($original->sourceType, $restored->sourceType);
    $this->assertSame($original->payload, $restored->payload);
    $this->assertSame($original->source, $restored->source);
    $this->assertEquals(
      $original->receivedAt->format(\DateTimeInterface::ATOM),
      $restored->receivedAt->format(\DateTimeInterface::ATOM),
    );
  }

}
