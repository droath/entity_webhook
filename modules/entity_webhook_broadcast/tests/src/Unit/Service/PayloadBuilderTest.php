<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
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

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('outbound_field_mapping')
      ->willReturn($this->fieldMappingStorage);

    $this->builder = new PayloadBuilder(
      $this->entityTypeManager,
      $this->mutationManager,
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
   * Tests that build maps a single field to its configured output key.
   *
   * @covers ::build
   */
  public function testBuildMapsSingleFieldToOutputKey(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_1');
    $entity = $this->createEntityWithField('title', [['value' => 'Hello World']]);

    $mapping = $this->createFieldMappingMock(
      entityField: 'title',
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
   * Tests that build maps multiple fields to their respective output keys.
   *
   * @covers ::build
   */
  public function testBuildMapsMultipleFieldsToTheirOutputKeys(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_2');

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->willReturnMap([
      ['title', TRUE],
      ['field_status', TRUE],
    ]);

    $titleList = $this->createFieldListMock([['value' => 'My Node']]);
    $statusList = $this->createFieldListMock([['value' => 1]]);

    $entity->method('get')->willReturnMap([
      ['title', $titleList],
      ['field_status', $statusList],
    ]);

    $titleMapping = $this->createFieldMappingMock(
      entityField: 'title',
      outputKey: 'name',
      mutationPlugin: '',
      mutationConfig: [],
    );
    $statusMapping = $this->createFieldMappingMock(
      entityField: 'field_status',
      outputKey: 'published',
      mutationPlugin: '',
      mutationConfig: [],
    );

    $ids = ['sub_2.name' => 'sub_2.name', 'sub_2.published' => 'sub_2.published'];
    $query = $this->createQueryMock($ids);
    $this->fieldMappingStorage->method('getQuery')->willReturn($query);
    $this->fieldMappingStorage
      ->method('loadMultiple')
      ->willReturn([$titleMapping, $statusMapping]);

    // Act
    $payload = $this->builder->build($entity, $subscription);

    // Assert
    $this->assertSame('My Node', $payload['name']);
    $this->assertSame(1, $payload['published']);
  }

  /**
   * Tests that build returns NULL for a field that does not exist on the entity.
   *
   * @covers ::build
   */
  public function testBuildReturnsNullForNonExistentEntityField(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_3');

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->with('nonexistent_field')->willReturn(FALSE);

    $mapping = $this->createFieldMappingMock(
      entityField: 'nonexistent_field',
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
   * Tests that build applies a FieldValueMutation plugin to the extracted value.
   *
   * @covers ::build
   */
  public function testBuildAppliesMutationPluginToExtractedValue(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_4');
    $entity = $this->createEntityWithField('title', [['value' => 'hello world']]);

    $mutationPlugin = $this->createMock(FieldValueMutationInterface::class);
    $mutationPlugin->method('mutate')
      ->with('hello world')
      ->willReturn('HELLO WORLD');

    $this->mutationManager->method('createInstance')
      ->with('to_uppercase', ['key' => 'config'])
      ->willReturn($mutationPlugin);

    $mapping = $this->createFieldMappingMock(
      entityField: 'title',
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
   * Tests that build passes the mutation config to createInstance.
   *
   * @covers ::build
   */
  public function testBuildPassesMutationConfigToPluginManager(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock('sub_5');
    $entity = $this->createEntityWithField('field_price', [['value' => '1999']]);

    $mutationPlugin = $this->createMock(FieldValueMutationInterface::class);
    $mutationPlugin->method('mutate')->willReturn(19.99);

    $expectedConfig = ['precision' => 2, 'currency' => 'USD'];
    $this->mutationManager->expects($this->once())
      ->method('createInstance')
      ->with('price_cents_to_decimal', $expectedConfig)
      ->willReturn($mutationPlugin);

    $mapping = $this->createFieldMappingMock(
      entityField: 'field_price',
      outputKey: 'price',
      mutationPlugin: 'price_cents_to_decimal',
      mutationConfig: $expectedConfig,
    );

    $query = $this->createQueryMock(['sub_5.price' => 'sub_5.price']);
    $this->fieldMappingStorage->method('getQuery')->willReturn($query);
    $this->fieldMappingStorage->method('loadMultiple')->willReturn([$mapping]);

    // Act
    $this->builder->build($entity, $subscription);
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
   * Creates a mock FieldableEntityInterface that returns a field with the given values.
   *
   * @param string $fieldName
   *   The field name.
   * @param array<int, array<string, mixed>> $values
   *   The raw field values as returned by FieldItemListInterface::getValue().
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The mock entity.
   */
  private function createEntityWithField(string $fieldName, array $values): FieldableEntityInterface {
    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->with($fieldName)->willReturn(TRUE);

    $fieldList = $this->createFieldListMock($values);
    $entity->method('get')->with($fieldName)->willReturn($fieldList);

    return $entity;
  }

  /**
   * Creates a mock FieldItemListInterface returning the given values.
   *
   * @param array<int, array<string, mixed>> $values
   *   The raw field values.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The mock field list.
   */
  private function createFieldListMock(array $values): FieldItemListInterface {
    $fieldList = $this->createMock(FieldItemListInterface::class);
    $fieldList->method('getValue')->willReturn($values);
    return $fieldList;
  }

  /**
   * Creates a mock OutboundFieldMappingInterface with the given properties.
   *
   * @param string $entityField
   *   The entity field machine name.
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
    string $entityField,
    string $outputKey,
    string $mutationPlugin,
    array $mutationConfig,
  ): OutboundFieldMappingInterface {
    $mapping = $this->createMock(OutboundFieldMappingInterface::class);
    $mapping->method('getEntityField')->willReturn($entityField);
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
