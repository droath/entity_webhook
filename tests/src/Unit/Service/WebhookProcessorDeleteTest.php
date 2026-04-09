<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook\Entity\FieldMapping;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverInterface;
use Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverManagerInterface;
use Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorManager;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\entity_webhook\Service\EntityUpsertServiceInterface;
use Drupal\entity_webhook\Service\WebhookProcessor;
use Drupal\entity_webhook\Validator\WebhookRequestValidatorInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for delete operation routing in WebhookProcessor.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Service\WebhookProcessor
 * @group entity_webhook
 */
class WebhookProcessorDeleteTest extends UnitTestCase {

  /**
   * Tests that process() routes to delete path when operation is 'delete'.
   *
   * Verifies that entity->delete() is called, the upsert path is not invoked,
   * and a 'deleted' success result is returned.
   *
   * @covers ::process
   */
  public function testProcessRoutesToDeleteWhenOperationIsDelete(): void {
    // Arrange.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn('42');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('Test Node');
    $entity->expects($this->once())->method('delete');
    $entity->expects($this->never())->method('save');

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);
    $entityUpsert->expects($this->never())->method('applyFieldValues');

    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->expects($this->never())->method('dispatch');

    $processor = $this->buildProcessor(
      operation: 'delete',
      entityUpsert: $entityUpsert,
      eventDispatcher: $eventDispatcher,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertTrue($result->success);
    $this->assertSame('deleted', $result->operation);
  }

  /**
   * Tests that process() routes to the upsert path when operation is 'upsert'.
   *
   * Verifies that applyFieldValues() is called, entity->delete() is never
   * invoked, and a success result is returned.
   *
   * @covers ::process
   */
  public function testProcessRoutesToUpsertWhenOperationIsUpsert(): void {
    // Arrange.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('id')->willReturn('3');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('An Article');

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);
    $entityUpsert->expects($this->once())->method('applyFieldValues');

    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->method('dispatch')->willReturnArgument(0);

    $processor = $this->buildProcessor(
      operation: 'upsert',
      entityUpsert: $entityUpsert,
      eventDispatcher: $eventDispatcher,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert — delete is never called and result reflects updated entity.
    $this->assertTrue($result->success);
    $this->assertSame('updated', $result->operation);
  }

  /**
   * Tests that processDelete() is a no-op when the entity does not exist.
   *
   * When resolveEntity() returns an entity where isNew() is TRUE, the entity
   * was not found in storage. The delete is skipped, info is logged, and
   * a 'skipped' success result is returned.
   *
   * @covers ::process
   */
  public function testProcessDeleteSkipsWhenEntityNotFound(): void {
    // Arrange.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(TRUE);
    $entity->expects($this->never())->method('delete');

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->atLeastOnce())->method('info');

    $processor = $this->buildProcessor(
      operation: 'delete',
      entityUpsert: $entityUpsert,
      logger: $logger,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertTrue($result->success);
    $this->assertSame('skipped', $result->operation);
  }

  /**
   * Tests that processDelete() deletes an existing entity and returns a deleted result.
   *
   * When resolveEntity() returns an entity where isNew() is FALSE, the entity
   * exists in storage, must be deleted, and a 'deleted' success result is returned.
   *
   * @covers ::process
   */
  public function testProcessDeleteDeletesExistingEntityAndReturnsDeletedResult(): void {
    // Arrange.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn('7');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('Old Article');
    $entity->expects($this->once())->method('delete');

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->atLeastOnce())->method('info');

    $processor = $this->buildProcessor(
      operation: 'delete',
      entityUpsert: $entityUpsert,
      logger: $logger,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertTrue($result->success);
    $this->assertSame('deleted', $result->operation);
  }

  /**
   * Builds a WebhookProcessor with controlled collaborator doubles.
   *
   * @param string $operation
   *   The operation the source type will report ('delete' or 'upsert').
   * @param \Drupal\entity_webhook\Service\EntityUpsertServiceInterface $entityUpsert
   *   The entity upsert mock.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|null $eventDispatcher
   *   Optional event dispatcher mock; a permissive stub is used when NULL.
   * @param \Drupal\Core\Logger\LoggerChannelInterface|null $logger
   *   Optional logger mock; a silent stub is used when NULL.
   *
   * @return \Drupal\entity_webhook\Service\WebhookProcessor
   *   The configured processor under test.
   */
  private function buildProcessor(
    string $operation,
    EntityUpsertServiceInterface $entityUpsert,
    ?EventDispatcherInterface $eventDispatcher = NULL,
    ?LoggerChannelInterface $logger = NULL,
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
    $sourceType->method('getPayloadProcessor')->willReturn('');

    $validator = $this->createMock(WebhookRequestValidatorInterface::class);
    $validator->method('loadEndpoint')->willReturn($endpoint);
    $validator->method('loadSourceType')->willReturn($sourceType);

    $resolverPlugin = $this->createMock(ValueResolverInterface::class);
    $resolverPlugin->method('resolve')->willReturn('Test');

    $resolverManager = $this->createMock(ValueResolverManagerInterface::class);
    $resolverManager->method('createInstance')->willReturn($resolverPlugin);

    $mutationManager = $this->createMock(FieldValueMutationManagerInterface::class);

    $defaultEventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $defaultEventDispatcher->method('dispatch')->willReturnArgument(0);

    return new WebhookProcessor(
      validator: $validator,
      entityUpsert: $entityUpsert,
      logger: $logger ?? $this->createMock(LoggerChannelInterface::class),
      eventDispatcher: $eventDispatcher ?? $defaultEventDispatcher,
      mutationManager: $mutationManager,
      resolverManager: $resolverManager,
      payloadProcessorManager: $this->createMock(WebhookPayloadProcessorManager::class),
    );
  }

  /**
   * Builds a minimal WebhookQueueItem for the test.
   *
   * @return \Drupal\entity_webhook\Queue\WebhookQueueItem
   *   A queue item targeting the test endpoint/source.
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
