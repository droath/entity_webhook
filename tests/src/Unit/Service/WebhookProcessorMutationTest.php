<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook\Entity\FieldMapping;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface;
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
 * Unit tests for FieldValueMutation integration in WebhookProcessor.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Service\WebhookProcessor
 * @group entity_webhook
 */
class WebhookProcessorMutationTest extends UnitTestCase {

  /**
   * Tests that a configured mutation plugin transforms the extracted value.
   *
   * @covers ::process
   */
  public function testMutationIsAppliedWhenPluginIsConfigured(): void {
    $mutatedValue = 'MUTATED';
    $extractedValue = 'original';

    $mutationPlugin = $this->createMock(FieldValueMutationInterface::class);
    $mutationPlugin->expects($this->once())
      ->method('mutate')
      ->with($extractedValue)
      ->willReturn($mutatedValue);

    $mutationManager = $this->createMock(FieldValueMutationManagerInterface::class);
    $mutationManager->expects($this->once())
      ->method('createInstance')
      ->with('string_replace', [])
      ->willReturn($mutationPlugin);

    $mapping = new FieldMapping(
      entityField: 'title',
      isIdentifier: FALSE,
      mutationPlugin: 'string_replace',
      mutationConfig: [],
      resolver: 'json_path',
      resolverConfig: ['path' => '$.name'],
    );

    $capturedValues = [];
    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($this->createMock(EntityInterface::class));
    $entityUpsert->method('applyFieldValues')
      ->willReturnCallback(function ($entity, $mappings, array $values) use (&$capturedValues): void {
        $capturedValues = $values;
      });

    $processor = $this->buildProcessor(
      extractedValue: $extractedValue,
      mapping: $mapping,
      mutationManager: $mutationManager,
      entityUpsert: $entityUpsert,
    );

    $processor->process($this->buildQueueItem());

    $this->assertSame($mutatedValue, $capturedValues['title'] ?? NULL);
  }

  /**
   * Tests that a mutation failure logs an error and returns the original value.
   *
   * @covers ::process
   */
  public function testMutationFailureLogsErrorAndReturnsOriginalValue(): void {
    $extractedValue = 'original';

    $mutationPlugin = $this->createMock(FieldValueMutationInterface::class);
    $mutationPlugin->method('mutate')->willThrowException(new \RuntimeException('Plugin exploded'));

    $mutationManager = $this->createMock(FieldValueMutationManagerInterface::class);
    $mutationManager->method('createInstance')->willReturn($mutationPlugin);

    $mapping = new FieldMapping(
      entityField: 'title',
      isIdentifier: FALSE,
      mutationPlugin: 'broken_plugin',
      mutationConfig: [],
      resolver: 'json_path',
      resolverConfig: ['path' => '$.name'],
    );

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->atLeastOnce())
      ->method('error');

    $capturedValues = [];
    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($this->createMock(EntityInterface::class));
    $entityUpsert->method('applyFieldValues')
      ->willReturnCallback(function ($entity, $mappings, array $values) use (&$capturedValues): void {
        $capturedValues = $values;
      });

    $processor = $this->buildProcessor(
      extractedValue: $extractedValue,
      mapping: $mapping,
      mutationManager: $mutationManager,
      entityUpsert: $entityUpsert,
      logger: $logger,
    );

    $processor->process($this->buildQueueItem());

    $this->assertSame($extractedValue, $capturedValues['title'] ?? NULL);
  }

  /**
   * Tests that no mutation is applied when mutationPlugin is an empty string.
   *
   * @covers ::process
   */
  public function testNoMutationAppliedWhenPluginIsEmpty(): void {
    $extractedValue = 'untouched';

    $mutationManager = $this->createMock(FieldValueMutationManagerInterface::class);
    $mutationManager->expects($this->never())->method('createInstance');

    $mapping = new FieldMapping(
      entityField: 'title',
      isIdentifier: FALSE,
      mutationPlugin: '',
      mutationConfig: [],
      resolver: 'json_path',
      resolverConfig: ['path' => '$.name'],
    );

    $capturedValues = [];
    $entityUpsert = $this->createMock(EntityUpsertServiceInterface::class);
    $entityUpsert->method('resolveEntity')->willReturn($this->createMock(EntityInterface::class));
    $entityUpsert->method('applyFieldValues')
      ->willReturnCallback(function ($entity, $mappings, array $values) use (&$capturedValues): void {
        $capturedValues = $values;
      });

    $processor = $this->buildProcessor(
      extractedValue: $extractedValue,
      mapping: $mapping,
      mutationManager: $mutationManager,
      entityUpsert: $entityUpsert,
    );

    $processor->process($this->buildQueueItem());

    $this->assertSame($extractedValue, $capturedValues['title'] ?? NULL);
  }

  /**
   * Builds a WebhookProcessor with controlled collaborator doubles.
   *
   * @param mixed $extractedValue
   *   The value the JSONPath extractor will return.
   * @param \Drupal\entity_webhook\Entity\FieldMapping $mapping
   *   The single field mapping to use.
   * @param \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $mutationManager
   *   The mutation manager mock.
   * @param \Drupal\entity_webhook\Service\EntityUpsertServiceInterface $entityUpsert
   *   The entity upsert mock.
   * @param \Drupal\Core\Logger\LoggerChannelInterface|null $logger
   *   Optional logger mock; a silent stub is used when NULL.
   *
   * @return \Drupal\entity_webhook\Service\WebhookProcessor
   *   The configured processor under test.
   */
  private function buildProcessor(
    mixed $extractedValue,
    FieldMapping $mapping,
    FieldValueMutationManagerInterface $mutationManager,
    EntityUpsertServiceInterface $entityUpsert,
    ?LoggerChannelInterface $logger = NULL,
  ): WebhookProcessor {
    $endpoint = $this->createMock(WebhookEndpointInterface::class);
    $endpoint->method('hasSourceType')->willReturn(TRUE);
    $endpoint->method('getTargetEntityTypeId')->willReturn('node');
    $endpoint->method('getTargetEntityBundle')->willReturn('article');

    $sourceType = $this->createMock(WebhookSourceTypeInterface::class);
    $sourceType->method('getFieldMappings')->willReturn([$mapping]);
    $sourceType->method('getPayloadProcessor')->willReturn('');
    $sourceType->method('getOperation')->willReturn('upsert');

    $validator = $this->createMock(WebhookRequestValidatorInterface::class);
    $validator->method('loadEndpoint')->willReturn($endpoint);
    $validator->method('loadSourceType')->willReturn($sourceType);

    $resolverPlugin = $this->createMock(ValueResolverInterface::class);
    $resolverPlugin->method('resolve')->willReturn($extractedValue);

    $resolverManager = $this->createMock(ValueResolverManagerInterface::class);
    $resolverManager->method('createInstance')->willReturn($resolverPlugin);

    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->method('dispatch')->willReturnArgument(0);

    return new WebhookProcessor(
      validator: $validator,
      entityUpsert: $entityUpsert,
      logger: $logger ?? $this->createMock(LoggerChannelInterface::class),
      eventDispatcher: $eventDispatcher,
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
