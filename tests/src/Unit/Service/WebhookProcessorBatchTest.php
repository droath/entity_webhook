<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook\Entity\FieldMapping;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\entity_webhook\Event\EntityWebhookEvents;
use Drupal\entity_webhook\Event\WebhookBatchCompleteEvent;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverInterface;
use Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverManagerInterface;
use Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorInterface;
use Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorManager;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\entity_webhook\Service\EntityUpsertServiceInterface;
use Drupal\entity_webhook\Service\WebhookProcessor;
use Drupal\entity_webhook\Validator\WebhookRequestValidatorInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for WebhookProcessor batch processing behaviour.
 *
 * Verifies that the payload processor pipeline splits payloads correctly,
 * dispatches the BATCH_COMPLETE event with accurate data, and remains
 * backwards-compatible when no processor is configured.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Service\WebhookProcessor
 * @group entity_webhook
 */
class WebhookProcessorBatchTest extends UnitTestCase {

  /**
   * Tests that a single payload is processed directly when no processor is set.
   *
   * When getPayloadProcessor() returns an empty string the processor should
   * fall through to the standard runOperation path, giving the same result
   * as before batch support was introduced. No BATCH_COMPLETE event should fire.
   *
   * @covers ::process
   */
  public function testProcessHandlesSinglePayloadWhenNoProcessorConfigured(): void {
    // Arrange.
    $entity = $this->buildEntityMock(isNew: TRUE);

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);

    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->expects($this->any())
      ->method('dispatch')
      ->willReturnCallback(function (object $event, string $eventName) {
        $this->assertNotSame(EntityWebhookEvents::BATCH_COMPLETE, $eventName);
        return $event;
      });

    $processor = $this->buildProcessor(
      operation: 'upsert',
      payloadProcessorId: '',
      entityUpsert: $entityUpsert,
      eventDispatcher: $eventDispatcher,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert: standard result, no batch involved.
    $this->assertTrue($result->success);
    $this->assertSame('created', $result->operation);
  }

  /**
   * Tests that each sub-payload is processed and BATCH_COMPLETE fires once.
   *
   * When the processor plugin returns three payloads the underlying
   * runOperation should be called three times and the BATCH_COMPLETE event
   * should be dispatched exactly once carrying all three results.
   *
   * @covers ::process
   */
  public function testProcessHandlesEachSubPayloadAndDispatchesBatchCompleteEvent(): void {
    // Arrange: processor returns 3 derived payloads.
    $subPayloads = [
      ['id' => 1, 'email' => 'a@example.com'],
      ['id' => 2, 'email' => 'b@example.com'],
      ['id' => 3, 'email' => 'c@example.com'],
    ];

    $processorPlugin = $this->createMock(WebhookPayloadProcessorInterface::class);
    $processorPlugin->method('process')->willReturn($subPayloads);

    $payloadProcessorManager = $this->createMock(WebhookPayloadProcessorManager::class);
    $payloadProcessorManager->method('createInstance')->willReturn($processorPlugin);

    // Entity upsert returns a new entity each time it is called.
    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')
      ->willReturnCallback(fn() => $this->buildEntityMock(isNew: TRUE));

    $dispatchedBatchEvent = NULL;
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->method('dispatch')
      ->willReturnCallback(function (object $event, string $eventName) use (&$dispatchedBatchEvent) {
        if ($eventName === EntityWebhookEvents::BATCH_COMPLETE) {
          $dispatchedBatchEvent = $event;
        }
        return $event;
      });

    $processor = $this->buildProcessor(
      operation: 'upsert',
      payloadProcessorId: 'array_iterator',
      entityUpsert: $entityUpsert,
      eventDispatcher: $eventDispatcher,
      payloadProcessorManager: $payloadProcessorManager,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert: last sub-result is returned.
    $this->assertTrue($result->success);

    // Assert: BATCH_COMPLETE event was dispatched with the correct data.
    $this->assertInstanceOf(WebhookBatchCompleteEvent::class, $dispatchedBatchEvent);
    $this->assertCount(3, $dispatchedBatchEvent->results);
    $this->assertSame('test_source', $dispatchedBatchEvent->sourceType);
    $this->assertSame('test_endpoint', $dispatchedBatchEvent->endpointId);
  }

  /**
   * Tests that an error result is returned when the processor yields no payloads.
   *
   * If the processor plugin returns an empty array no runOperation calls should
   * happen and no BATCH_COMPLETE event should fire.
   *
   * @covers ::process
   */
  public function testProcessReturnsErrorWhenPayloadProcessorYieldsNoPayloads(): void {
    // Arrange: processor returns nothing.
    $processorPlugin = $this->createMock(WebhookPayloadProcessorInterface::class);
    $processorPlugin->method('process')->willReturn([]);

    $payloadProcessorManager = $this->createMock(WebhookPayloadProcessorManager::class);
    $payloadProcessorManager->method('createInstance')->willReturn($processorPlugin);

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->expects($this->never())->method('resolveEntity');

    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->expects($this->never())->method('dispatch');

    $processor = $this->buildProcessor(
      operation: 'upsert',
      payloadProcessorId: 'array_iterator',
      entityUpsert: $entityUpsert,
      eventDispatcher: $eventDispatcher,
      payloadProcessorManager: $payloadProcessorManager,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertFalse($result->success);
    $this->assertNotNull($result->error);
  }

  /**
   * Tests that BATCH_COMPLETE carries the original (unsplit) payload.
   *
   * The event must hold the raw payload from the queue item, not any derived
   * sub-payload, so that subscribers can correlate the full incoming record.
   *
   * @covers ::process
   */
  public function testBatchCompleteEventContainsOriginalPayload(): void {
    // Arrange.
    $originalPayload = ['email' => 'customer@example.com', 'addresses' => [['id' => 5]]];
    $subPayloads = [['id' => 5, 'email' => 'customer@example.com']];

    $processorPlugin = $this->createMock(WebhookPayloadProcessorInterface::class);
    $processorPlugin->method('process')->willReturn($subPayloads);

    $payloadProcessorManager = $this->createMock(WebhookPayloadProcessorManager::class);
    $payloadProcessorManager->method('createInstance')->willReturn($processorPlugin);

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($this->buildEntityMock(isNew: FALSE));

    $capturedEvent = NULL;
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->method('dispatch')
      ->willReturnCallback(function (object $event, string $eventName) use (&$capturedEvent) {
        if ($eventName === EntityWebhookEvents::BATCH_COMPLETE) {
          $capturedEvent = $event;
        }
        return $event;
      });

    $processor = $this->buildProcessor(
      operation: 'upsert',
      payloadProcessorId: 'array_iterator',
      entityUpsert: $entityUpsert,
      eventDispatcher: $eventDispatcher,
      payloadProcessorManager: $payloadProcessorManager,
    );

    $queueItem = new WebhookQueueItem(
      endpointId: 'test_endpoint',
      sourceType: 'test_source',
      payload: $originalPayload,
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    // Act.
    $processor->process($queueItem);

    // Assert: the original (unmodified) payload is in the event.
    $this->assertInstanceOf(WebhookBatchCompleteEvent::class, $capturedEvent);
    $this->assertSame($originalPayload, $capturedEvent->originalPayload);
    $this->assertSame('test_source', $capturedEvent->sourceType);
    $this->assertSame('test_endpoint', $capturedEvent->endpointId);
  }

  /**
   * Builds an EntityInterface mock with controllable isNew and id behaviour.
   *
   * @param bool $isNew
   *   Whether the entity should report itself as new.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The mock entity.
   */
  private function buildEntityMock(bool $isNew): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn($isNew);
    $entity->method('id')->willReturn('1');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('Test Node');
    return $entity;
  }

  /**
   * Builds a WebhookProcessor with controlled collaborators.
   *
   * @param string $operation
   *   Operation the source type will report ('upsert' or 'delete').
   * @param string $payloadProcessorId
   *   Payload processor plugin ID; empty string means no processor configured.
   * @param \Drupal\entity_webhook\Service\EntityUpsertServiceInterface $entityUpsert
   *   The entity upsert double.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|null $eventDispatcher
   *   Event dispatcher double; a permissive stub is used when NULL.
   * @param \Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorManager|null $payloadProcessorManager
   *   Payload processor manager double; a plain mock is used when NULL.
   *
   * @return \Drupal\entity_webhook\Service\WebhookProcessor
   *   The configured processor under test.
   */
  private function buildProcessor(
    string $operation,
    string $payloadProcessorId,
    EntityUpsertServiceInterface $entityUpsert,
    ?EventDispatcherInterface $eventDispatcher = NULL,
    ?WebhookPayloadProcessorManager $payloadProcessorManager = NULL,
  ): WebhookProcessor {
    $mapping = new FieldMapping(
      entityField: 'title',
      isIdentifier: TRUE,
      resolver: 'json_path',
      resolverConfig: ['path' => '$.name'],
    );

    $endpoint = $this->createMock(WebhookEndpointInterface::class);
    $endpoint->method('hasSourceType')->willReturn(TRUE);
    $endpoint->method('getTargetEntityTypeId')->willReturn('node');
    $endpoint->method('getTargetEntityBundle')->willReturn('article');

    $sourceType = $this->createMock(WebhookSourceTypeInterface::class);
    $sourceType->method('getOperation')->willReturn($operation);
    $sourceType->method('getFieldMappings')->willReturn([$mapping]);
    $sourceType->method('getPayloadProcessor')->willReturn($payloadProcessorId);
    $sourceType->method('getPayloadProcessorConfig')->willReturn([]);

    $validator = $this->createMock(WebhookRequestValidatorInterface::class);
    $validator->method('loadEndpoint')->willReturn($endpoint);
    $validator->method('loadSourceType')->willReturn($sourceType);

    $resolverPlugin = $this->createMock(ValueResolverInterface::class);
    $resolverPlugin->method('resolve')->willReturn('Test');

    $resolverManager = $this->createMock(ValueResolverManagerInterface::class);
    $resolverManager->method('createInstance')->willReturn($resolverPlugin);

    $defaultEventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $defaultEventDispatcher->method('dispatch')->willReturnArgument(0);

    return new WebhookProcessor(
      validator: $validator,
      entityUpsert: $entityUpsert,
      logger: $this->createMock(LoggerChannelInterface::class),
      eventDispatcher: $eventDispatcher ?? $defaultEventDispatcher,
      mutationManager: $this->createMock(FieldValueMutationManagerInterface::class),
      resolverManager: $resolverManager,
      payloadProcessorManager: $payloadProcessorManager ?? $this->createMock(WebhookPayloadProcessorManager::class),
    );
  }

  /**
   * Builds a minimal WebhookQueueItem for tests.
   *
   * @return \Drupal\entity_webhook\Queue\WebhookQueueItem
   *   A queue item targeting the test endpoint and source type.
   */
  private function buildQueueItem(): WebhookQueueItem {
    return new WebhookQueueItem(
      endpointId: 'test_endpoint',
      sourceType: 'test_source',
      payload: ['name' => 'Test'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );
  }

}
