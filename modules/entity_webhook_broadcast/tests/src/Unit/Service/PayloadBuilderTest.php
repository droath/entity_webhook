<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverInterface;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface;
use Drupal\entity_webhook_broadcast\Service\PayloadBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the PayloadBuilder service.
 *
 * @group entity_webhook_broadcast
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Service\PayloadBuilder
 */
class PayloadBuilderTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The mocked field value mutation plugin manager.
   */
  private FieldValueMutationManagerInterface $mutationManager;

  /**
   * The mocked outbound value resolver plugin manager.
   */
  private OutboundValueResolverManagerInterface $resolverManager;

  /**
   * The mocked storage for outbound_field_mapping entities.
   */
  private EntityStorageInterface $fieldMappingStorage;

  /**
   * The service under test.
   */
  private PayloadBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldMappingStorage = $this->createMock(EntityStorageInterface::class);
    $this->mutationManager = $this->createMock(FieldValueMutationManagerInterface::class);
    $this->resolverManager = $this->createMock(OutboundValueResolverManagerInterface::class);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('outbound_field_mapping')
      ->willReturn($this->fieldMappingStorage);

    $this->builder = new PayloadBuilder(
      $this->entityTypeManager,
      $this->mutationManager,
      $this->resolverManager,
    );
  }

  /**
   * Tests that build returns an empty array when no field mappings exist.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyArrayWhenNoFieldMappingsExist(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_1');
    $entity = $this->createMock(FieldableEntityInterface::class);

    $query = $this->createQueryMock([]);
    $this->fieldMappingStorage->method('getQuery')->willReturn($query);
    $this->fieldMappingStorage->method('loadMultiple')->willReturn([]);

    // Act
    $payload = $this->builder->build($entity, $subscription);

    // Assert
    $this->assertSame([], $payload);
  }

  /**
   * Tests that build delegates to the resolver plugin and maps its output to the key.
   *
   * @covers ::build
   */
  public function testBuildDelegatesToResolverPlugin(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_1');
    $entity = $this->createMock(EntityInterface::class);

    $resolver = $this->createMock(OutboundValueResolverInterface::class);
    $resolver->method('resolve')->with($entity)->willReturn('Hello World');

    $this->resolverManager->method('createInstance')
      ->with('entity_field', ['entity_field' => 'title'])
      ->willReturn($resolver);

    $mapping = $this->createFieldMappingMock(
      resolver: 'entity_field',
      resolverConfig: ['entity_field' => 'title'],
      outputKey: 'node_title',
      mutationPlugin: '',
      mutationConfig: [],
    );

    $query = $this->createQueryMock(['sub_1.node_title' => 'sub_1.node_title']);
    $this->fieldMappingStorage->method('getQuery')->willReturn($query);
    $this->fieldMappingStorage->method('loadMultiple')->willReturn([$mapping]);

    // Act
    $payload = $this->builder->build($entity, $subscription);

    // Assert
    $this->assertSame(['node_title' => 'Hello World'], $payload);
  }

  /**
   * Tests that build maps multiple field mappings to their respective output keys.
   *
   * @covers ::build
   */
  public function testBuildMapsMultipleFieldMappingsToTheirOutputKeys(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_2');
    $entity = $this->createMock(EntityInterface::class);

    $titleResolver = $this->createMock(OutboundValueResolverInterface::class);
    $titleResolver->method('resolve')->willReturn('My Node');

    $statusResolver = $this->createMock(OutboundValueResolverInterface::class);
    $statusResolver->method('resolve')->willReturn(1);

    $this->resolverManager->method('createInstance')
      ->willReturnMap([
        ['entity_field', ['entity_field' => 'title'], $titleResolver],
        ['entity_field', ['entity_field' => 'field_status'], $statusResolver],
      ]);

    $titleMapping = $this->createFieldMappingMock(
      resolver: 'entity_field',
      resolverConfig: ['entity_field' => 'title'],
      outputKey: 'name',
      mutationPlugin: '',
      mutationConfig: [],
    );
    $statusMapping = $this->createFieldMappingMock(
      resolver: 'entity_field',
      resolverConfig: ['entity_field' => 'field_status'],
      outputKey: 'published',
      mutationPlugin: '',
      mutationConfig: [],
    );

    $ids = ['sub_2.name' => 'sub_2.name', 'sub_2.published' => 'sub_2.published'];
    $query = $this->createQueryMock($ids);
    $this->fieldMappingStorage->method('getQuery')->willReturn($query);
    $this->fieldMappingStorage->method('loadMultiple')->willReturn([$titleMapping, $statusMapping]);

    // Act
    $payload = $this->builder->build($entity, $subscription);

    // Assert
    $this->assertSame('My Node', $payload['name']);
    $this->assertSame(1, $payload['published']);
  }

  /**
   * Tests that build applies a FieldValueMutation plugin to the resolved value.
   *
   * @covers ::build
   */
  public function testBuildCombinesResolverAndMutation(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_4');
    $entity = $this->createMock(EntityInterface::class);

    $resolver = $this->createMock(OutboundValueResolverInterface::class);
    $resolver->method('resolve')->willReturn('hello world');

    $this->resolverManager->method('createInstance')
      ->with('entity_field', ['entity_field' => 'title'])
      ->willReturn($resolver);

    $mutationPlugin = $this->createMock(FieldValueMutationInterface::class);
    $mutationPlugin->method('mutate')
      ->with('hello world')
      ->willReturn('HELLO WORLD');

    $this->mutationManager->method('createInstance')
      ->with('to_uppercase', ['key' => 'config'])
      ->willReturn($mutationPlugin);

    $mapping = $this->createFieldMappingMock(
      resolver: 'entity_field',
      resolverConfig: ['entity_field' => 'title'],
      outputKey: 'title_upper',
      mutationPlugin: 'to_uppercase',
      mutationConfig: ['key' => 'config'],
    );

    $query = $this->createQueryMock(['sub_4.title_upper' => 'sub_4.title_upper']);
    $this->fieldMappingStorage->method('getQuery')->willReturn($query);
    $this->fieldMappingStorage->method('loadMultiple')->willReturn([$mapping]);

    // Act
    $payload = $this->builder->build($entity, $subscription);

    // Assert
    $this->assertSame('HELLO WORLD', $payload['title_upper']);
  }

  /**
   * Tests that build returns an empty payload when subscription ID is empty.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyPayloadForSubscriptionWithEmptyId(): void {
    // Arrange: subscription returns NULL id (not yet saved).
    $subscription = $this->createMock(OutboundSubscriptionInterface::class);
    $subscription->method('id')->willReturn(NULL);

    $entity = $this->createMock(FieldableEntityInterface::class);

    // Act
    $payload = $this->builder->build($entity, $subscription);

    // Assert: no fields mapped because subscription ID is empty.
    $this->assertSame([], $payload);
  }

  /**
   * Tests that build returns NULL for output key when resolver plugin ID is empty.
   *
   * @covers ::build
   */
  public function testBuildReturnsNullWhenResolverPluginIdIsEmpty(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_3');
    $entity = $this->createMock(EntityInterface::class);

    $mapping = $this->createFieldMappingMock(
      resolver: '',
      resolverConfig: [],
      outputKey: 'missing',
      mutationPlugin: '',
      mutationConfig: [],
    );

    $query = $this->createQueryMock(['sub_3.missing' => 'sub_3.missing']);
    $this->fieldMappingStorage->method('getQuery')->willReturn($query);
    $this->fieldMappingStorage->method('loadMultiple')->willReturn([$mapping]);

    // Act
    $payload = $this->builder->build($entity, $subscription);

    // Assert
    $this->assertArrayHasKey('missing', $payload);
    $this->assertNull($payload['missing']);
  }

  /**
   * Creates a mock OutboundSubscriptionInterface with the given ID.
   *
   * @param string $id
   *   The subscription ID.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface
   *   The mock subscription.
   */
  private function createSubscriptionMock(string $id): OutboundSubscriptionInterface {
    $subscription = $this->createMock(OutboundSubscriptionInterface::class);
    $subscription->method('id')->willReturn($id);

    return $subscription;
  }

  /**
   * Creates a mock OutboundFieldMappingInterface with the given properties.
   *
   * @param string $resolver
   *   The resolver plugin ID.
   * @param array<string, mixed> $resolverConfig
   *   The resolver plugin configuration.
   * @param string $outputKey
   *   The output JSON key.
   * @param string $mutationPlugin
   *   The mutation plugin ID (empty string for none).
   * @param array<string, mixed> $mutationConfig
   *   The mutation plugin configuration.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface
   *   The mock field mapping.
   */
  private function createFieldMappingMock(
    string $resolver,
    array $resolverConfig,
    string $outputKey,
    string $mutationPlugin,
    array $mutationConfig,
  ): OutboundFieldMappingInterface {
    $mapping = $this->createMock(OutboundFieldMappingInterface::class);
    $mapping->method('getResolver')->willReturn($resolver);
    $mapping->method('getResolverConfig')->willReturn($resolverConfig);
    $mapping->method('getOutputKey')->willReturn($outputKey);
    $mapping->method('getMutationPlugin')->willReturn($mutationPlugin);
    $mapping->method('getMutationConfig')->willReturn($mutationConfig);

    return $mapping;
  }

  /**
   * Creates a mock query that returns the given IDs.
   *
   * @param array<string, string> $ids
   *   The IDs to return.
   *
   * @return object
   *   A mock query stub.
   */
  private function createQueryMock(array $ids): object {
    $query = new class($ids) {

      /**
       * Constructs the mock query.
       *
       * @param array<string, string> $ids
       *   The IDs to return from execute().
       */
      public function __construct(private readonly array $ids) {
      }

      /**
       * Returns $this for method chaining.
       */
      public function accessCheck(bool $check): static {
        return $this;
      }

      /**
       * Returns $this for method chaining.
       */
      public function condition(string $field, mixed $value): static {
        return $this;
      }

      /**
       * Returns the configured IDs.
       *
       * @return array<string, string>
       */
      public function execute(): array {
        return $this->ids;
      }

    };

    return $query;
  }

}
