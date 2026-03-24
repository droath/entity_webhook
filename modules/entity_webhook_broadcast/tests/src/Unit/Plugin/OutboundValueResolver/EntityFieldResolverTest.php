<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Plugin\OutboundValueResolver;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\EntityFieldResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the EntityFieldResolver plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\EntityFieldResolver
 * @group entity_webhook_broadcast
 */
class EntityFieldResolverTest extends UnitTestCase {

  /**
   * The mocked entity field manager (required by plugin constructor).
   */
  private EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
  }

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\EntityFieldResolver
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): EntityFieldResolver {
    return new EntityFieldResolver(
      $configuration,
      'entity_field',
      ['id' => 'entity_field', 'label' => 'Entity Field'],
      $this->entityFieldManager,
    );
  }

  /**
   * Creates a mock FieldableEntityInterface with the given field data.
   *
   * @param string $fieldName
   *   The field name the entity reports as existing.
   * @param array<int, array<string, mixed>> $values
   *   The raw values returned by FieldItemListInterface::getValue().
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The configured entity mock.
   */
  private function createEntityWithField(string $fieldName, array $values): FieldableEntityInterface {
    $fieldList = $this->createMock(FieldItemListInterface::class);
    $fieldList->method('getValue')->willReturn($values);

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->willReturnMap([
      [$fieldName, TRUE],
    ]);
    $entity->method('get')->with($fieldName)->willReturn($fieldList);

    return $entity;
  }

  /**
   * Tests that resolve returns a scalar value for a single-value field.
   *
   * When a field returns exactly one item with one key, the scalar is unwrapped
   * from the nested array structure.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsSingleValueForSingleValueField(): void {
    // Arrange
    $plugin = $this->createPlugin(['entity_field' => 'title']);
    $entity = $this->createEntityWithField('title', [['value' => 'Hello World']]);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertSame('Hello World', $result);
  }

  /**
   * Tests that resolve returns an array for a multi-value field.
   *
   * When a field returns more than one item, an array of unwrapped scalars is
   * returned.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsArrayForMultiValueField(): void {
    // Arrange
    $plugin = $this->createPlugin(['entity_field' => 'field_tags']);
    $entity = $this->createEntityWithField('field_tags', [
      ['value' => 'php'],
      ['value' => 'drupal'],
      ['value' => 'testing'],
    ]);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertSame(['php', 'drupal', 'testing'], $result);
  }

  /**
   * Tests that resolve returns NULL when the entity does not have the field.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullForNonExistentField(): void {
    // Arrange
    $plugin = $this->createPlugin(['entity_field' => 'field_nonexistent']);

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->method('hasField')->willReturn(FALSE);
    $entity->expects($this->never())->method('get');

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that resolve returns NULL when the field exists but has no values.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullForEmptyField(): void {
    // Arrange
    $plugin = $this->createPlugin(['entity_field' => 'field_optional']);
    $entity = $this->createEntityWithField('field_optional', []);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that resolve returns NULL when no field name is configured.
   *
   * An empty entity_field configuration means no field can be resolved.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenEntityFieldConfigurationIsEmpty(): void {
    // Arrange
    $plugin = $this->createPlugin(['entity_field' => '']);

    $entity = $this->createMock(FieldableEntityInterface::class);
    $entity->expects($this->never())->method('hasField');
    $entity->expects($this->never())->method('get');

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that defaultConfiguration provides an empty entity_field key.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesEmptyEntityField(): void {
    // Arrange
    $plugin = $this->createPlugin();

    // Act
    $config = $plugin->defaultConfiguration();

    // Assert
    $this->assertSame(['entity_field' => ''], $config);
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
    $this->assertSame('Entity Field', $label);
  }

  /**
   * Tests that resolve returns a compound array for a multi-key single field item.
   *
   * When a field has one item with multiple keys (e.g., a reference field with
   * target_id and target_type), the whole array is returned unchanged.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsCompoundArrayForMultiKeyFieldItem(): void {
    // Arrange
    $plugin = $this->createPlugin(['entity_field' => 'field_reference']);
    $entity = $this->createEntityWithField('field_reference', [
      ['target_id' => 42, 'target_type' => 'node'],
    ]);

    // Act
    $result = $plugin->resolve($entity);

    // Assert: multi-key array is returned as-is (not unwrapped).
    $this->assertSame(['target_id' => 42, 'target_type' => 'node'], $result);
  }

}
