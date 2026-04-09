<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Event;

use Drupal\entity_webhook\Event\EntityWebhookEvents;
use Drupal\entity_webhook\Event\WebhookBatchCompleteEvent;
use Drupal\entity_webhook\Service\WebhookProcessResult;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the WebhookBatchCompleteEvent value object.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Event\WebhookBatchCompleteEvent
 * @group entity_webhook
 */
class WebhookBatchCompleteEventTest extends UnitTestCase {

  /**
   * Tests that all constructor arguments are accessible via public properties.
   *
   * @covers ::__construct
   */
  public function testConstructorStoresAllPropertiesAccessibleViaPublicProperties(): void {
    // Arrange.
    $originalPayload = ['email' => 'user@example.com', 'addresses' => [['id' => 1]]];
    $sourceType = 'address_shopify';
    $endpointId = 'shopify_endpoint';
    $result1 = WebhookProcessResult::error('failure message');
    $result2 = new WebhookProcessResult(
      success: TRUE,
      operation: 'created',
      entityId: '42',
      entityTypeId: 'remote_address',
      bundle: 'remote_address',
      label: 'Test Address',
      error: NULL,
    );
    $results = [$result1, $result2];

    // Act.
    $event = new WebhookBatchCompleteEvent(
      originalPayload: $originalPayload,
      sourceType: $sourceType,
      endpointId: $endpointId,
      results: $results,
    );

    // Assert.
    $this->assertSame($originalPayload, $event->originalPayload);
    $this->assertSame($sourceType, $event->sourceType);
    $this->assertSame($endpointId, $event->endpointId);
    $this->assertSame($results, $event->results);
  }

  /**
   * Tests that an empty results array is stored correctly.
   *
   * @covers ::__construct
   */
  public function testConstructorStoresEmptyResultsArray(): void {
    // Arrange.
    $event = new WebhookBatchCompleteEvent(
      originalPayload: [],
      sourceType: 'some_source',
      endpointId: 'some_endpoint',
      results: [],
    );

    // Act / Assert.
    $this->assertSame([], $event->results);
    $this->assertSame([], $event->originalPayload);
  }

  /**
   * Tests that properties are readonly and cannot be overwritten.
   *
   * @covers ::__construct
   */
  public function testPropertiesAreReadonly(): void {
    // Arrange.
    $event = new WebhookBatchCompleteEvent(
      originalPayload: ['id' => 1],
      sourceType: 'source',
      endpointId: 'endpoint',
      results: [],
    );

    // Assert: attempting to modify a readonly property throws an error.
    $this->expectException(\Error::class);
    // @phpstan-ignore-next-line
    $event->sourceType = 'modified';
  }

  /**
   * Tests that the BATCH_COMPLETE event name constant exists and is a string.
   *
   * @covers \Drupal\entity_webhook\Event\EntityWebhookEvents::BATCH_COMPLETE
   */
  public function testBatchCompleteEventNameConstantExistsOnEntityWebhookEvents(): void {
    // Act / Assert.
    $this->assertIsString(EntityWebhookEvents::BATCH_COMPLETE);
    $this->assertNotEmpty(EntityWebhookEvents::BATCH_COMPLETE);
  }

  /**
   * Tests that the BATCH_COMPLETE constant value is 'entity_webhook.batch_complete'.
   *
   * This verifies the event name is stable and matches any subscribers that
   * have been registered against a specific string.
   *
   * @covers \Drupal\entity_webhook\Event\EntityWebhookEvents::BATCH_COMPLETE
   */
  public function testBatchCompleteConstantHasExpectedValue(): void {
    // Assert.
    $this->assertSame('entity_webhook.batch_complete', EntityWebhookEvents::BATCH_COMPLETE);
  }

}
