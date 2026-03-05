<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_webhook\Event\EntityWebhookPreSaveEvent;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for EntityWebhookPreSaveEvent.
 *
 * @group entity_webhook
 */
class EntityWebhookPreSaveEventTest extends UnitTestCase {

  /**
   * Tests that the event exposes its constructor properties correctly.
   */
  public function testEventExposesProperties(): void {
    $entity = $this->createMock(EntityInterface::class);
    $payload = ['id' => 42, 'name' => 'Test'];

    $event = new EntityWebhookPreSaveEvent(
      entity: $entity,
      payload: $payload,
      endpointId: 'my_endpoint',
      sourceType: 'my_source',
      isNew: TRUE,
    );

    $this->assertSame($entity, $event->entity);
    $this->assertSame($payload, $event->payload);
    $this->assertSame('my_endpoint', $event->endpointId);
    $this->assertSame('my_source', $event->sourceType);
    $this->assertTrue($event->isNew);
  }

  /**
   * Tests that abort() causes isAborted() to return TRUE.
   */
  public function testAbortFlagDefaultsFalseAndCanBeSet(): void {
    $event = new EntityWebhookPreSaveEvent(
      entity: $this->createMock(EntityInterface::class),
      payload: [],
      endpointId: 'ep',
      sourceType: 'st',
      isNew: FALSE,
    );

    $this->assertFalse($event->isAborted());

    $event->abort();

    $this->assertTrue($event->isAborted());
  }

}
