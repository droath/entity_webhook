<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Plugin\OutboundValueResolver;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\FieldComponentResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the FieldComponentResolver plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\FieldComponentResolver
 * @group entity_webhook_broadcast
 */
class FieldComponentResolverTest extends UnitTestCase {

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
   * @return \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\FieldComponentResolver
   *   The plugin instance.
   */
  private function createPlugin(array $configuration = []): FieldComponentResolver {
    return new FieldComponentResolver(
      $configuration,
      'field_component',
      ['id' => 'field_component', 'label' => 'Field Component'],
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
   * Tests the happy path: extracts a named component from a multi-key field value.
   *
   * A price field stores values as arrays with 'number' and 'currency_code' keys.
   * When the component key is 'number', the numeric string is returned.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsComponentFromFieldValue(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => 'field_price',
      'component' => 'number',
    ]);
    $priceFieldValues = [['number' => '19.99', 'currency_code' => 'USD']];
    $entity = $this->createEntityWithField('field_price', $priceFieldValues);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertSame('19.99', $result);
  }

  /**
   * Tests that resolve returns NULL when entity_field config is empty.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenEntityFieldConfigIsEmpty(): void {
    // Arrange
    $plugin = $this->createPlugin(['entity_field' => '', 'component' => 'number']);

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
      'entity_field' => 'field_price',
      'component' => 'number',
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
   * Tests that resolve returns NULL when the field has no values.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenFieldIsEmpty(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => 'field_price',
      'component' => 'number',
    ]);
    $entity = $this->createEntityWithField('field_price', []);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that resolve returns NULL when the component key does not exist.
   *
   * When the first field value array does not contain the configured component
   * key, no value can be extracted.
   *
   * @covers ::resolve
   */
  public function testResolveReturnsNullWhenComponentKeyDoesNotExist(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => 'field_price',
      'component' => 'nonexistent_key',
    ]);
    $entity = $this->createEntityWithField('field_price', [['number' => '19.99', 'currency_code' => 'USD']]);

    // Act
    $result = $plugin->resolve($entity);

    // Assert
    $this->assertNull($result);
  }

  /**
   * Tests that defaultConfiguration provides empty entity_field and component keys.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesEmptyFields(): void {
    // Arrange
    $plugin = $this->createPlugin();

    // Act
    $config = $plugin->defaultConfiguration();

    // Assert
    $this->assertSame(['entity_field' => '', 'component' => ''], $config);
  }

  /**
   * Tests that buildConfigurationForm only shows fields with multiple properties.
   *
   * Fields with a single property (e.g. 'value' only) are excluded because
   * there is no meaningful component to extract. Only fields with two or more
   * properties (e.g. body with value/summary/format) should appear.
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationFormFiltersToMultiPropertyFields(): void {
    // Arrange
    $plugin = $this->createPlugin([
      'entity_field' => '',
      'component' => '',
    ]);

    $bodyStorage = $this->createMock(FieldStorageDefinitionInterface::class);
    $bodyStorage->method('getPropertyNames')->willReturn(['value', 'summary', 'format']);
    $bodyField = $this->createMock(FieldDefinitionInterface::class);
    $bodyField->method('getFieldStorageDefinition')->willReturn($bodyStorage);
    $bodyField->method('getLabel')->willReturn('Body');

    $imageStorage = $this->createMock(FieldStorageDefinitionInterface::class);
    $imageStorage->method('getPropertyNames')->willReturn(['target_id', 'alt', 'title', 'width', 'height']);
    $imageField = $this->createMock(FieldDefinitionInterface::class);
    $imageField->method('getFieldStorageDefinition')->willReturn($imageStorage);
    $imageField->method('getLabel')->willReturn('Image');

    $titleStorage = $this->createMock(FieldStorageDefinitionInterface::class);
    $titleStorage->method('getPropertyNames')->willReturn(['value']);
    $titleField = $this->createMock(FieldDefinitionInterface::class);
    $titleField->method('getFieldStorageDefinition')->willReturn($titleStorage);
    $titleField->method('getLabel')->willReturn('Title');

    $statusStorage = $this->createMock(FieldStorageDefinitionInterface::class);
    $statusStorage->method('getPropertyNames')->willReturn(['value']);
    $statusField = $this->createMock(FieldDefinitionInterface::class);
    $statusField->method('getFieldStorageDefinition')->willReturn($statusStorage);
    $statusField->method('getLabel')->willReturn('Published');

    $this->entityFieldManager->method('getFieldDefinitions')
      ->with('node', 'article')
      ->willReturn([
        'body' => $bodyField,
        'field_image' => $imageField,
        'title' => $titleField,
        'status' => $statusField,
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
    $this->assertArrayHasKey('body', $options);
    $this->assertArrayHasKey('field_image', $options);
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
    $this->assertSame('Field Component', $label);
  }

}
