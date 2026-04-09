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
 * Unit tests verifying WebhookProcessor returns WebhookProcessResult values.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Service\WebhookProcessor
 * @group entity_webhook
 */
class WebhookProcessorResultTest extends UnitTestCase {

  /**
   * Tests that process() returns an error result when the endpoint is missing.
   *
   * @covers ::process
   */
  public function testProcessReturnsErrorWhenEndpointNotFound(): void {
    // Arrange.
    $validator = $this->createMock(WebhookRequestValidatorInterface::class);
    $validator->method('loadEndpoint')->willReturn(NULL);

    $processor = $this->buildProcessorWithValidator($validator);

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertFalse($result->success);
    $this->assertNotNull($result->error);
  }

  /**
   * Tests that process() returns a success result with 'created' on new entity.
   *
   * @covers ::process
   */
  public function testProcessReturnsCreatedResultForNewEntity(): void {
    // Arrange.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(TRUE);
    $entity->method('id')->willReturn('1');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('New Node');

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);

    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->method('dispatch')->willReturnArgument(0);

    $processor = $this->buildProcessor(
      operation: 'upsert',
      entityUpsert: $entityUpsert,
      eventDispatcher: $eventDispatcher,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertTrue($result->success);
    $this->assertSame('created', $result->operation);
  }

  /**
   * Tests that process() returns a success result with 'updated' for existing entity.
   *
   * @covers ::process
   */
  public function testProcessReturnsUpdatedResultForExistingEntity(): void {
    // Arrange.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('id')->willReturn('5');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('Existing Node');

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);

    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->method('dispatch')->willReturnArgument(0);

    $processor = $this->buildProcessor(
      operation: 'upsert',
      entityUpsert: $entityUpsert,
      eventDispatcher: $eventDispatcher,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertTrue($result->success);
    $this->assertSame('updated', $result->operation);
  }

  /**
   * Tests that process() returns a skipped result when delete target not found.
   *
   * @covers ::process
   */
  public function testProcessReturnsSkippedWhenDeleteTargetNotFound(): void {
    // Arrange.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(TRUE);

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);

    $processor = $this->buildProcessor(
      operation: 'delete',
      entityUpsert: $entityUpsert,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertTrue($result->success);
    $this->assertSame('skipped', $result->operation);
  }

  /**
   * Tests that process() returns a deleted result when entity is found and deleted.
   *
   * @covers ::process
   */
  public function testProcessReturnsDeletedResultWhenEntityDeleted(): void {
    // Arrange.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('id')->willReturn('9');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('Deleted Node');
    $entity->expects($this->once())->method('delete');

    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($entity);

    $processor = $this->buildProcessor(
      operation: 'delete',
      entityUpsert: $entityUpsert,
    );

    // Act.
    $result = $processor->process($this->buildQueueItem());

    // Assert.
    $this->assertTrue($result->success);
    $this->assertSame('deleted', $result->operation);
  }

  /**
   * Builds a WebhookProcessor with a custom validator stub.
   *
   * @param \Drupal\entity_webhook\Validator\WebhookRequestValidatorInterface $validator
   *   The validator to use.
   *
   * @return \Drupal\entity_webhook\Service\WebhookProcessor
   *   The processor under test.
   */
  private function buildProcessorWithValidator(WebhookRequestValidatorInterface $validator): WebhookProcessor {
    return new WebhookProcessor(
      validator: $validator,
      entityUpsert: $this->createMock(EntityUpsertServiceInterface::class),
      logger: $this->createMock(LoggerChannelInterface::class),
      eventDispatcher: $this->createMock(EventDispatcherInterface::class),
      mutationManager: $this->createMock(FieldValueMutationManagerInterface::class),
      resolverManager: $this->createMock(ValueResolverManagerInterface::class),
      payloadProcessorManager: $this->createMock(WebhookPayloadProcessorManager::class),
    );
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
   *
   * @return \Drupal\entity_webhook\Service\WebhookProcessor
   *   The configured processor under test.
   */
  private function buildProcessor(
    string $operation,
    EntityUpsertServiceInterface $entityUpsert,
    ?EventDispatcherInterface $eventDispatcher = NULL,
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
      logger: $this->createMock(LoggerChannelInterface::class),
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
