<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Queue;

use Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the OutboundQueueItem value object.
 *
 * @group entity_webhook_broadcast
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem
 */
class OutboundQueueItemTest extends UnitTestCase {

  /**
   * Tests that all constructor properties are publicly readable.
   */
  public function testConstructorExposesAllProperties(): void {
    // Arrange
    $dispatchedAt = new \DateTimeImmutable('2026-03-20T10:00:00+00:00');
    $payload = ['title' => 'Test Node', 'status' => TRUE];

    // Act
    $item = new OutboundQueueItem(
      endpointId: 'my_endpoint',
      subscriptionId: 'my_subscription',
      entityTypeId: 'node',
      entityId: '42',
      event: 'insert',
      payload: $payload,
      deliveryLogId: 'log_001',
      attempt: 1,
      dispatchedAt: $dispatchedAt,
    );

    // Assert
    $this->assertSame('my_endpoint', $item->endpointId);
    $this->assertSame('my_subscription', $item->subscriptionId);
    $this->assertSame('node', $item->entityTypeId);
    $this->assertSame('42', $item->entityId);
    $this->assertSame('insert', $item->event);
    $this->assertSame($payload, $item->payload);
    $this->assertSame('log_001', $item->deliveryLogId);
    $this->assertSame(1, $item->attempt);
    $this->assertSame($dispatchedAt, $item->dispatchedAt);
  }

  /**
   * Tests that deliveryLogId can be NULL for items before log integration.
   */
  public function testConstructorAcceptsNullDeliveryLogId(): void {
    // Arrange + Act
    $item = new OutboundQueueItem(
      endpointId: 'ep',
      subscriptionId: 'sub',
      entityTypeId: 'node',
      entityId: '1',
      event: 'update',
      payload: [],
      deliveryLogId: NULL,
      attempt: 1,
      dispatchedAt: new \DateTimeImmutable(),
    );

    // Assert
    $this->assertNull($item->deliveryLogId);
  }

  /**
   * Tests that toArray serializes all properties using the expected array keys.
   */
  public function testToArraySerializesAllPropertiesWithExpectedKeys(): void {
    // Arrange
    $dispatchedAt = new \DateTimeImmutable('2026-03-20T10:00:00+00:00');
    $payload = ['id' => 99, 'tags' => ['php', 'drupal']];

    $item = new OutboundQueueItem(
      endpointId: 'ep_1',
      subscriptionId: 'sub_1',
      entityTypeId: 'node',
      entityId: '99',
      event: 'delete',
      payload: $payload,
      deliveryLogId: NULL,
      attempt: 2,
      dispatchedAt: $dispatchedAt,
    );

    // Act
    $array = $item->toArray();

    // Assert
    $this->assertSame('ep_1', $array['endpoint_id']);
    $this->assertSame('sub_1', $array['subscription_id']);
    $this->assertSame('node', $array['entity_type_id']);
    $this->assertSame('99', $array['entity_id']);
    $this->assertSame('delete', $array['event']);
    $this->assertSame($payload, $array['payload']);
    $this->assertNull($array['delivery_log_id']);
    $this->assertSame(2, $array['attempt']);
    $this->assertSame(
      $dispatchedAt->format(\DateTimeInterface::ATOM),
      $array['dispatched_at'],
    );
  }

  /**
   * Tests that fromArray constructs an item with all fields from the array.
   */
  public function testFromArrayConstructsItemWithAllFields(): void {
    // Arrange
    $data = [
      'endpoint_id' => 'ep_abc',
      'subscription_id' => 'sub_xyz',
      'entity_type_id' => 'user',
      'entity_id' => '7',
      'event' => 'update',
      'payload' => ['name' => 'Alice'],
      'delivery_log_id' => 'dlg_99',
      'attempt' => 3,
      'dispatched_at' => '2026-03-20T08:30:00+00:00',
    ];

    // Act
    $item = OutboundQueueItem::fromArray($data);

    // Assert
    $this->assertSame('ep_abc', $item->endpointId);
    $this->assertSame('sub_xyz', $item->subscriptionId);
    $this->assertSame('user', $item->entityTypeId);
    $this->assertSame('7', $item->entityId);
    $this->assertSame('update', $item->event);
    $this->assertSame(['name' => 'Alice'], $item->payload);
    $this->assertSame('dlg_99', $item->deliveryLogId);
    $this->assertSame(3, $item->attempt);
    $this->assertSame(
      '2026-03-20T08:30:00+00:00',
      $item->dispatchedAt->format(\DateTimeInterface::ATOM),
    );
  }

  /**
   * Tests that fromArray maps null delivery_log_id to a null property.
   */
  public function testFromArrayWithNullDeliveryLogIdPreservesNull(): void {
    // Arrange
    $data = [
      'endpoint_id' => 'ep',
      'subscription_id' => 'sub',
      'entity_type_id' => 'node',
      'entity_id' => '1',
      'event' => 'insert',
      'payload' => [],
      'delivery_log_id' => NULL,
      'attempt' => 1,
      'dispatched_at' => '2026-03-20T00:00:00+00:00',
    ];

    // Act
    $item = OutboundQueueItem::fromArray($data);

    // Assert
    $this->assertNull($item->deliveryLogId);
  }

  /**
   * Tests that fromArray defaults attempt to 1 when the key is missing.
   */
  public function testFromArrayDefaultsAttemptToOneWhenMissing(): void {
    // Arrange
    $data = [
      'endpoint_id' => 'ep',
      'subscription_id' => 'sub',
      'entity_type_id' => 'node',
      'entity_id' => '1',
      'event' => 'insert',
      'payload' => [],
      'delivery_log_id' => NULL,
      'dispatched_at' => '2026-03-20T00:00:00+00:00',
    ];

    // Act
    $item = OutboundQueueItem::fromArray($data);

    // Assert
    $this->assertSame(1, $item->attempt);
  }

  /**
   * Tests that fromArray falls back to the current time for a malformed date.
   */
  public function testFromArrayFallsBackToCurrentTimeForMalformedDate(): void {
    // Arrange
    $before = new \DateTimeImmutable();

    $data = [
      'endpoint_id' => 'ep',
      'subscription_id' => 'sub',
      'entity_type_id' => 'node',
      'entity_id' => '1',
      'event' => 'insert',
      'payload' => [],
      'delivery_log_id' => NULL,
      'attempt' => 1,
      'dispatched_at' => 'not-a-valid-date@@##!!',
    ];

    // Act
    $item = OutboundQueueItem::fromArray($data);

    $after = new \DateTimeImmutable();

    // Assert: dispatchedAt should fall between before and after
    $this->assertGreaterThanOrEqual(
      $before->getTimestamp(),
      $item->dispatchedAt->getTimestamp(),
    );
    $this->assertLessThanOrEqual(
      $after->getTimestamp(),
      $item->dispatchedAt->getTimestamp(),
    );
  }

  /**
   * Tests that a full toArray/fromArray roundtrip preserves all data exactly.
   */
  public function testToArrayFromArrayRoundtripPreservesAllData(): void {
    // Arrange
    $original = new OutboundQueueItem(
      endpointId: 'roundtrip_ep',
      subscriptionId: 'roundtrip_sub',
      entityTypeId: 'commerce_order',
      entityId: '1001',
      event: 'update',
      payload: ['total' => 99.99, 'currency' => 'USD', 'items' => [1, 2, 3]],
      deliveryLogId: 'dlg_42',
      attempt: 2,
      dispatchedAt: new \DateTimeImmutable('2026-01-15T12:30:00+00:00'),
    );

    // Act
    $restored = OutboundQueueItem::fromArray($original->toArray());

    // Assert
    $this->assertSame($original->endpointId, $restored->endpointId);
    $this->assertSame($original->subscriptionId, $restored->subscriptionId);
    $this->assertSame($original->entityTypeId, $restored->entityTypeId);
    $this->assertSame($original->entityId, $restored->entityId);
    $this->assertSame($original->event, $restored->event);
    $this->assertSame($original->payload, $restored->payload);
    $this->assertSame($original->deliveryLogId, $restored->deliveryLogId);
    $this->assertSame($original->attempt, $restored->attempt);
    $this->assertSame(
      $original->dispatchedAt->format(\DateTimeInterface::ATOM),
      $restored->dispatchedAt->format(\DateTimeInterface::ATOM),
    );
  }

  /**
   * Tests that the value object is immutable (readonly class).
   */
  public function testValueObjectIsImmutable(): void {
    // Arrange
    $item = new OutboundQueueItem(
      endpointId: 'ep',
      subscriptionId: 'sub',
      entityTypeId: 'node',
      entityId: '1',
      event: 'insert',
      payload: [],
      deliveryLogId: NULL,
      attempt: 1,
      dispatchedAt: new \DateTimeImmutable(),
    );

    // Assert: readonly properties cannot be modified
    $this->expectException(\Error::class);
    // @phpstan-ignore-next-line
    $item->endpointId = 'mutated';
  }

}
