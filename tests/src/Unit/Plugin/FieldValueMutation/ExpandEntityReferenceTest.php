<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\FieldValueMutation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\ExpandEntityReference;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the ExpandEntityReference plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\FieldValueMutation\ExpandEntityReference
 * @group entity_webhook
 */
class ExpandEntityReferenceTest extends UnitTestCase {

  /**
   * Creates a plugin instance with minimal mocks.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   Optional entity type manager mock.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface|null $entityDisplayRepository
   *   Optional entity display repository mock.
   *
   * @return \Drupal\entity_webhook\Plugin\FieldValueMutation\ExpandEntityReference
   *   The plugin instance.
   */
  private function createPlugin(
    array $configuration = [],
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?EntityDisplayRepositoryInterface $entityDisplayRepository = NULL,
  ): ExpandEntityReference {
    $entityTypeManager ??= $this->createMock(EntityTypeManagerInterface::class);
    $entityDisplayRepository ??= $this->createMock(EntityDisplayRepositoryInterface::class);

    return new ExpandEntityReference(
      $configuration,
      'expand_entity_reference',
      ['id' => 'expand_entity_reference', 'label' => 'Expand Entity Reference'],
      $entityTypeManager,
      $entityDisplayRepository,
    );
  }

  /**
   * Tests that mutate returns unchanged value when input is not a numeric scalar or array.
   *
   * Non-numeric strings, NULL, and floats must pass through without modification.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsUnchangedValueForUnsupportedInputType(): void {
    $plugin = $this->createPlugin([
      'target_type' => 'node',
      'view_mode' => 'default',
    ]);

    $this->assertSame('a string', $plugin->mutate('a string'));
    $this->assertNull($plugin->mutate(NULL));
    $this->assertSame(3.14, $plugin->mutate(3.14));
  }

  /**
   * Tests that mutate expands a single integer ID into a structured object.
   *
   * @covers ::mutate
   */
  public function testMutateExpandsSingleIntegerIdIntoObject(): void {
    $entity = $this->buildEntityMock('node', 'article', [
      'title' => 'Test Node',
      'status' => 1,
    ]);

    $display = $this->buildDisplayMock(['title', 'status']);

    $entityTypeManager = $this->buildEntityTypeManagerMock('node', [42 => $entity], $display);
    $entityDisplayRepository = $this->createMock(EntityDisplayRepositoryInterface::class);

    $plugin = $this->createPlugin(
      ['target_type' => 'node', 'view_mode' => 'webhook'],
      $entityTypeManager,
      $entityDisplayRepository,
    );

    $result = $plugin->mutate(42);

    $this->assertSame([['title' => 'Test Node', 'status' => 1]], $result);
  }

  /**
   * Tests that mutate expands a single string ID (PayloadBuilder format) into a structured object.
   *
   * PayloadBuilder::extractFieldValue() unwraps single-key arrays, so entity
   * reference fields with one value arrive as a string like "1" rather than int 1.
   *
   * @covers ::mutate
   */
  public function testMutateExpandsSingleStringIdIntoObject(): void {
    $entity = $this->buildEntityMock('node', 'article', ['title' => 'Test Node']);
    $display = $this->buildDisplayMock(['title']);

    $entityTypeManager = $this->buildEntityTypeManagerMock('node', [42 => $entity], $display);
    $entityDisplayRepository = $this->createMock(EntityDisplayRepositoryInterface::class);

    $plugin = $this->createPlugin(
      ['target_type' => 'node', 'view_mode' => 'webhook'],
      $entityTypeManager,
      $entityDisplayRepository,
    );

    $result = $plugin->mutate('42');

    $this->assertSame([['title' => 'Test Node']], $result);
  }

  /**
   * Tests that mutate expands a flat array of string IDs (PayloadBuilder format).
   *
   * PayloadBuilder::extractFieldValue() unwraps multi-value entity reference
   * fields into a flat array of numeric strings like ["1", "2"] rather than
   * [['target_id' => 1], ['target_id' => 2]].
   *
   * @covers ::mutate
   */
  public function testMutateExpandsFlatArrayOfStringIdsIntoArrayOfObjects(): void {
    $entity1 = $this->buildEntityMock('node', 'article', ['title' => 'First']);
    $entity2 = $this->buildEntityMock('node', 'article', ['title' => 'Second']);
    $display = $this->buildDisplayMock(['title']);

    $entityTypeManager = $this->buildEntityTypeManagerMock(
      'node',
      [10 => $entity1, 20 => $entity2],
      $display,
    );
    $entityDisplayRepository = $this->createMock(EntityDisplayRepositoryInterface::class);

    $plugin = $this->createPlugin(
      ['target_type' => 'node', 'view_mode' => 'webhook'],
      $entityTypeManager,
      $entityDisplayRepository,
    );

    $result = $plugin->mutate(['10', '20']);

    $this->assertSame([
      ['title' => 'First'],
      ['title' => 'Second'],
    ], $result);
  }

  /**
   * Tests that mutate expands a multi-value array of target_id arrays into objects.
   *
   * @covers ::mutate
   */
  public function testMutateExpandsMultiValueArrayIntoArrayOfObjects(): void {
    $entity1 = $this->buildEntityMock('node', 'article', ['title' => 'First']);
    $entity2 = $this->buildEntityMock('node', 'article', ['title' => 'Second']);
    $display = $this->buildDisplayMock(['title']);

    $entityTypeManager = $this->buildEntityTypeManagerMock(
      'node',
      [10 => $entity1, 20 => $entity2],
      $display,
    );
    $entityDisplayRepository = $this->createMock(EntityDisplayRepositoryInterface::class);

    $plugin = $this->createPlugin(
      ['target_type' => 'node', 'view_mode' => 'webhook'],
      $entityTypeManager,
      $entityDisplayRepository,
    );

    $result = $plugin->mutate([['target_id' => 10], ['target_id' => 20]]);

    $this->assertSame([
      ['title' => 'First'],
      ['title' => 'Second'],
    ], $result);
  }

  /**
   * Tests that mutate returns the raw ID when no entity is found.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsRawIdWhenEntityNotFound(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->with([99])->willReturn([]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $plugin = $this->createPlugin(
      ['target_type' => 'node', 'view_mode' => 'webhook'],
      $entityTypeManager,
    );

    $result = $plugin->mutate(99);

    $this->assertSame([99], $result);
  }

  /**
   * Tests that mutate returns the raw ID when display has no components.
   *
   * @covers ::mutate
   */
  public function testMutateReturnsRawIdWhenDisplayHasNoComponents(): void {
    $entity = $this->buildEntityMock('node', 'article', []);
    $display = $this->buildDisplayMock([]);

    $entityTypeManager = $this->buildEntityTypeManagerMock('node', [5 => $entity], $display);

    $plugin = $this->createPlugin(
      ['target_type' => 'node', 'view_mode' => 'default'],
      $entityTypeManager,
    );

    $result = $plugin->mutate(5);

    $this->assertSame([5], $result);
  }

  /**
   * Tests that mutate recursively expands nested entity references.
   *
   * The node has a field_author entity reference field pointing to a user.
   * When the user entity type also has a matching view display, the plugin
   * should recursively expand the nested reference into structured data
   * instead of returning the raw target_id integer.
   *
   * @covers ::mutate
   */
  public function testMutateExpandsNestedEntityReferences(): void {
    // Arrange: user entity with name and mail fields.
    $userEntity = $this->createMock(ContentEntityInterface::class);
    $userEntity->method('getEntityTypeId')->willReturn('user');
    $userEntity->method('bundle')->willReturn('user');
    $userEntity->method('get')->willReturnCallback(function (string $fieldName): FieldItemListInterface {
      $fieldList = $this->createMock(FieldItemListInterface::class);
      $fieldList->method('getValue')->willReturn(match ($fieldName) {
        'name' => [['John']],
        'mail' => [['john@example.com']],
        default => [],
      });
      return $fieldList;
    });

    // Arrange: node entity whose field_author holds a reference to user ID 7.
    $userEntityId = 7;
    $nodeEntityId = 42;

    $fieldAuthorStorageDefinition = $this->createMock(FieldStorageDefinitionInterface::class);
    $fieldAuthorStorageDefinition->method('getSetting')->with('target_type')->willReturn('user');

    $fieldAuthorDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldAuthorDefinition->method('getType')->willReturn('entity_reference');
    $fieldAuthorDefinition->method('getFieldStorageDefinition')->willReturn($fieldAuthorStorageDefinition);

    $nodeEntity = $this->createMock(ContentEntityInterface::class);
    $nodeEntity->method('getEntityTypeId')->willReturn('node');
    $nodeEntity->method('bundle')->willReturn('article');
    $nodeEntity->method('get')->willReturnCallback(function (string $fieldName) use ($userEntityId, $fieldAuthorDefinition): FieldItemListInterface {
      $fieldList = $this->createMock(FieldItemListInterface::class);
      $fieldList->method('getValue')->willReturn(match ($fieldName) {
        'title' => [['Test Article']],
        'field_author' => [['target_id' => $userEntityId]],
        default => [],
      });
      if ($fieldName === 'field_author') {
        $fieldList->method('getFieldDefinition')->willReturn($fieldAuthorDefinition);
      }
      return $fieldList;
    });

    // Arrange: storages for node, user, and entity_view_display.
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('loadMultiple')->with([$nodeEntityId])->willReturn([$nodeEntityId => $nodeEntity]);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadMultiple')->with([$userEntityId])->willReturn([$userEntityId => $userEntity]);

    // Arrange: node display exposes title and field_author; user display exposes name and mail.
    $nodeDisplay = $this->buildDisplayMock(['title', 'field_author']);
    $userDisplay = $this->buildDisplayMock(['name', 'mail']);

    $displayStorage = $this->createMock(EntityStorageInterface::class);
    $displayStorage->method('load')->willReturnMap([
      ['node.article.webhook', $nodeDisplay],
      ['user.user.webhook', $userDisplay],
    ]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      ['node', $nodeStorage],
      ['user', $userStorage],
      ['entity_view_display', $displayStorage],
    ]);

    $plugin = $this->createPlugin(
      ['target_type' => 'node', 'view_mode' => 'webhook'],
      $entityTypeManager,
    );

    // Act.
    $result = $plugin->mutate($nodeEntityId);

    // Assert: field_author is recursively expanded into the user's fields.
    $this->assertSame([
      [
        'title' => 'Test Article',
        'field_author' => [
          [
            'name' => 'John',
            'mail' => 'john@example.com',
          ],
        ],
      ],
    ], $result);
  }

  /**
   * Tests that defaultConfiguration provides expected defaults.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesExpectedDefaults(): void {
    $plugin = $this->createPlugin();

    $this->assertSame(['target_type' => '', 'view_mode' => ''], $plugin->defaultConfiguration());
  }

  /**
   * Tests that buildConfigurationForm reads target_type from user input during AJAX rebuild.
   *
   * When the plugin is re-instantiated from saved config (target_type: ''),
   * but the user has selected 'node' in the form, the view mode options must
   * be built for 'node', not all entity types.
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationFormUsesUserInputTargetTypeForViewModeOptions(): void {
    $entityDisplayRepository = $this->createMock(EntityDisplayRepositoryInterface::class);
    $entityDisplayRepository
      ->expects($this->once())
      ->method('getViewModeOptions')
      ->with('node')
      ->willReturn(['default' => 'Default', 'teaser' => 'Teaser']);
    $entityDisplayRepository
      ->expects($this->never())
      ->method('getAllViewModes');

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager
      ->method('getDefinitions')
      ->willReturn([]);

    $plugin = $this->createPlugin(
      ['target_type' => '', 'view_mode' => ''],
      $entityTypeManager,
      $entityDisplayRepository,
    );

    $formState = $this->createMock(FormStateInterface::class);
    $formState
      ->method('getUserInput')
      ->willReturn(['mutation_config' => ['target_type' => 'node']]);

    $form = $plugin->buildConfigurationForm([], $formState);

    $this->assertSame(['default' => 'Default', 'teaser' => 'Teaser'], $form['view_mode']['#options']);
  }

  /**
   * Tests that buildConfigurationForm adds required empty option to both selects.
   *
   * Empty options prevent "submitted value not allowed" errors when the plugin
   * is first selected via AJAX before the user has chosen values.
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationFormAddsEmptyOptionToBothSelects(): void {
    $entityDisplayRepository = $this->createMock(EntityDisplayRepositoryInterface::class);
    $entityDisplayRepository
      ->method('getAllViewModes')
      ->willReturn([]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager
      ->method('getDefinitions')
      ->willReturn([]);

    $plugin = $this->createPlugin(
      ['target_type' => '', 'view_mode' => ''],
      $entityTypeManager,
      $entityDisplayRepository,
    );

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getUserInput')->willReturn([]);

    $form = $plugin->buildConfigurationForm([], $formState);

    $this->assertSame('', $form['target_type']['#empty_value']);
    $this->assertSame('', $form['view_mode']['#empty_value']);
    $this->assertArrayHasKey('#empty_option', $form['target_type']);
    $this->assertArrayHasKey('#empty_option', $form['view_mode']);
  }

  /**
   * Builds a mock entity with the given type, bundle, and field values.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param array<string, mixed> $fieldValues
   *   A map of field name to scalar value.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The configured entity mock.
   */
  private function buildEntityMock(string $entityTypeId, string $bundle, array $fieldValues): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entityTypeId);
    $entity->method('bundle')->willReturn($bundle);

    $entity->method('get')->willReturnCallback(function (string $fieldName) use ($fieldValues): FieldItemListInterface {
      $fieldList = $this->createMock(FieldItemListInterface::class);
      $rawValue = $fieldValues[$fieldName] ?? NULL;
      $fieldList->method('getValue')->willReturn($rawValue !== NULL ? [[$rawValue]] : []);
      return $fieldList;
    });

    return $entity;
  }

  /**
   * Builds a mock EntityViewDisplay with the given visible field names.
   *
   * @param string[] $fieldNames
   *   The field names that should appear as display components.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The configured display mock.
   */
  private function buildDisplayMock(array $fieldNames): EntityViewDisplayInterface {
    $display = $this->createMock(EntityViewDisplayInterface::class);

    $components = array_fill_keys($fieldNames, ['weight' => 0]);
    $display->method('getComponents')->willReturn($components);

    return $display;
  }

  /**
   * Builds an entity type manager mock wired to a storage and display.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param array<int, \Drupal\Core\Entity\ContentEntityInterface> $entities
   *   Entities keyed by ID.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display to return for the entity's bundle.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The configured entity type manager mock.
   */
  private function buildEntityTypeManagerMock(
    string $entityTypeId,
    array $entities,
    EntityViewDisplayInterface $display,
  ): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($entities);

    $displayStorage = $this->createMock(EntityStorageInterface::class);
    $displayStorage->method('load')->willReturn($display);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnMap([
      [$entityTypeId, $storage],
      ['entity_view_display', $displayStorage],
    ]);

    return $entityTypeManager;
  }

}
