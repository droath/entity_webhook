<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Plugin\OutboundValueResolver;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\EntityReferenceFieldResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the EntityReferenceFieldResolver plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\EntityReferenceFieldResolver
 * @group entity_webhook_broadcast
 */
class EntityReferenceFieldResolverTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The mocked entity field manager (required by plugin constructor).
   */
  private EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
  }

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\EntityReferenceFieldResolver
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): EntityReferenceFieldResolver {
    return new EntityReferenceFieldResolver(
      $configuration,
      'entity_reference_field',
      ['id' => 'entity_reference_field', 'label' => 'Entity Reference Field'],
      $this->entityTypeManager,
      $this->entityFieldManager,
    );
  }

  /**
   * Creates a mock source entity with an entity reference field.
   *
   * The field returns the given raw values and exposes a field storage
   * definition whose target_type setting returns $targetType.
   *
   * @param string $fieldName
   *   The reference field name on the source entity.
   * @param array<int, array<string, mixed>> $values
   *   Raw values returned by FieldItemListInterface::getValue().
   * @param string $targetType
   *   The entity type ID stored in the field storage definition.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The configured entity mock.
   */
  private function createEntityWithReferenceField(
    string $fieldName,
    array $values,
    string $targetType = 'node',
  ): FieldableEntityInterface {
    $fieldList = $this->createMock(FieldItemListInterface::class);
    $fieldList->method('getValue')->willReturn($values);

    $fieldStorageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $fieldStorageDefinition->method('getSetting')
      ->with('target_type')
      ->willReturn($targetType);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getFieldStorageDefinition')
      ->willReturn($fieldStorageDefinition);

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->willReturnMap([
      [$fieldName, TRUE],
    ]);
    $entity->method('get')->with($fieldName)->willReturn($fieldList);
    $entity->method('getFieldDefinition')->with($fieldName)->willReturn($fieldDefinition);

    return $entity;
  }

  /**
   * Creates a mock referenced entity with the given target field data.
   *
   * @param string $targetField
   *   The field name on the referenced entity.
   * @param array<int, array<string, mixed>> $values
   *   Raw values returned by FieldItemListInterface::getValue().
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The configured referenced entity mock.
   */
  private function createReferencedEntityWithField(string $targetField, array $values): FieldableEntityInterface {
    $fieldList = $this->createMock(FieldItemListInterface::class);
    $fieldList->method('getValue')->willReturn($values);

    $referencedEntity = $this->createMock(FieldableEntityInterface::class);
    $referencedEntity->method('hasField')->willReturnMap([
      [$targetField, TRUE],
    ]);
    $referencedEntity->method('get')->with($targetField)->willReturn($fieldList);

    return $referencedEntity;
  }

  /**
   * Configures the entity type manager to return $referencedEntity for a load.
   *
   * @param string $targetType
   *   The entity type ID of the storage to mock.
   * @param int|string $targetId
   *   The entity ID passed to load().
   * @param \Drupal\Core\Entity\FieldableEntityInterface $referencedEntity
   *   The entity to return from storage::load().
   */
  private function configureEntityStorage(
    string $targetType,
    int|string $targetId,
    FieldableEntityInterface $referencedEntity,
  ): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with($targetId)->willReturn($referencedEntity);

    $this->entityTypeManager->method('getStorage')
      ->with($targetType)
      ->willReturn($storage);
  }

  /**
   * Tests the happy path: resolves a field value through an entity reference.
   *
   * Given a source entity with a reference field pointing to a node, and that
   * node having the target field populated, the resolved value is returned.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsValueFromReferencedEntity(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => 'field_author',
      'target_field' => 'field_bio',
    ]);

    $referencedEntity = $this->createReferencedEntityWithField('field_bio', [['value' => 'Author biography text']]);
    $sourceEntity = $this->createEntityWithReferenceField('field_author', [['target_id' => 7]], 'node');
    $this->configureEntityStorage('node', 7, $referencedEntity);

    // Act
    $result = $plugin->resolve($sourceEntity);

    // Assert
    $this->assertSame('Author biography text', $result);
  }

  /**
   * Tests that resolve returns NULL when entity_field config is empty.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenEntityFieldConfigIsEmpty(): void {
    // Arrange
    $plugin = $this->createPlugin(['entity_field' => '', 'target_field' => 'field_bio']);

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->expects($this->never())->method('hasField');
    $entity->expects($this->never())->method('get');

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that resolve returns NULL when the entity does not have the field.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenEntityDoesNotHaveField(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => 'field_author',
      'target_field' => 'field_bio',
    ]);

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->willReturn(FALSE);
    $entity->expects($this->never())->method('get');

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that resolve returns NULL when the reference field has no values.
   *
   * An unpopulated entity reference field cannot point to any referenced entity.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenReferenceFieldIsEmpty(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => 'field_author',
      'target_field' => 'field_bio',
    ]);

    $sourceEntity = $this->createEntityWithReferenceField('field_author', []);

    // Act
    $result = $plugin->resolve($sourceEntity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that resolve returns NULL when target_field config is empty.
   *
   * Even when the reference resolves to an entity, an empty target_field
   * configuration means no field can be read from the referenced entity.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenTargetFieldConfigIsEmpty(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => 'field_author',
      'target_field' => '',
    ]);

    $referencedEntity = $this->createReferencedEntityWithField('field_bio', [['value' => 'Some text']]);
    $sourceEntity = $this->createEntityWithReferenceField('field_author', [['target_id' => 7]], 'node');
    $this->configureEntityStorage('node', 7, $referencedEntity);

    // Act
    $result = $plugin->resolve($sourceEntity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that resolve returns NULL when the referenced entity lacks target field.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenReferencedEntityDoesNotHaveTargetField(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => 'field_author',
      'target_field' => 'field_nonexistent',
    ]);

    $referencedEntity = $this->createMock(FieldableEntityInterface::class);
    $referencedEntity->method('hasField')->willReturn(FALSE);
    $referencedEntity->expects($this->never())->method('get');

    $sourceEntity = $this->createEntityWithReferenceField('field_author', [['target_id' => 7]], 'node');
    $this->configureEntityStorage('node', 7, $referencedEntity);

    // Act
    $result = $plugin->resolve($sourceEntity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that defaultConfiguration provides empty entity_field and target_field.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesEmptyFields(): void {
    // Arrange
    $plugin = $this->createPlugin();

    // Act
    $config = $plugin->defaultConfiguration();

    // Assert
    $this->assertSame(['entity_field' => '', 'target_field' => ''], $config);
  }

  /**
   * Tests that buildConfigurationForm only shows entity reference fields.
   *
   * Non-reference fields (e.g. string, boolean) must be filtered out so the
   * user only sees fields that actually reference other entities.
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationFormFiltersToEntityReferenceFields(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => '',
      'target_field' => '',
    ]);

    $referenceField = $this->createMock(FieldDefinitionInterface::class);
    $referenceField->method('getType')->willReturn('entity_reference');
    $referenceField->method('getLabel')->willReturn('Author');

    $revisionReferenceField = $this->createMock(FieldDefinitionInterface::class);
    $revisionReferenceField->method('getType')->willReturn('entity_revision_reference');
    $revisionReferenceField->method('getLabel')->willReturn('Revision Ref');

    $stringField = $this->createMock(FieldDefinitionInterface::class);
    $stringField->method('getType')->willReturn('string');
    $stringField->method('getLabel')->willReturn('Title');

    $booleanField = $this->createMock(FieldDefinitionInterface::class);
    $booleanField->method('getType')->willReturn('boolean');
    $booleanField->method('getLabel')->willReturn('Published');

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn([
        'field_author' => $referenceField,
        'field_revision_ref' => $revisionReferenceField,
        'title' => $stringField,
        'status' => $booleanField,
      ]);

    $formState = new FormState();
    $formState->set('outbound_resolver_context', [
      'entity_type_id' => 'node',
      'bundle' => 'article',
    ]);

    // Act
    $form = $plugin->buildConfigurationForm([], $formState);

    // Assert
    $options = $form['entity_field']['#options'];
    $this->assertCount(2, $options);
    $this->assertArrayHasKey('field_author', $options);
    $this->assertArrayHasKey('field_revision_ref', $options);
    $this->assertArrayNotHasKey('title', $options);
    $this->assertArrayNotHasKey('status', $options);
  }

  /**
   * Tests that label returns the plugin label from the definition.
   *
   * @covers ::label
   */
  public function testLabelReturnsPluginDefinitionLabel(): void {
    // Arrange
    $plugin = $this->createPlugin();

    // Act
    $label = $plugin->label();

    // Assert
    $this->assertSame('Entity Reference Field', $label);
  }

}
