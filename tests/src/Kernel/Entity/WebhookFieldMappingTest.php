<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Entity;

use Drupal\entity_webhook\Entity\FieldMapping;
use Drupal\entity_webhook\Entity\WebhookFieldMapping;
use Drupal\entity_webhook\Entity\WebhookFieldMappingInterface;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for WebhookFieldMapping config entity CRUD and cascade deletion.
 *
 * @group entity_webhook
 */
class WebhookFieldMappingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook'];

  /**
   * Tests that a WebhookFieldMapping can be created and loaded with all properties.
   */
  public function testCreateAndLoadWebhookFieldMapping(): void {
    // Arrange
    WebhookFieldMapping::create([
      'id' => 'shopify_order.field_external_id',
      'label' => 'External Order ID',
      'source_type' => 'shopify_order',
      'entity_field' => 'field_external_id',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.id'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    $loaded = WebhookFieldMapping::load('shopify_order.field_external_id');

    // Assert
    $this->assertInstanceOf(WebhookFieldMappingInterface::class, $loaded);
    $this->assertSame('shopify_order.field_external_id', $loaded->id());
    $this->assertSame('External Order ID', $loaded->label());
    $this->assertSame('shopify_order', $loaded->getSourceTypeId());
    $this->assertSame('field_external_id', $loaded->getEntityField());
    $this->assertTrue($loaded->isIdentifier());
    $this->assertSame('json_path', $loaded->getResolver());
    $this->assertSame(['path' => '$.id'], $loaded->getResolverConfig());
    $this->assertSame('', $loaded->getMutationPlugin());
    $this->assertSame([], $loaded->getMutationConfig());
  }

  /**
   * Tests that the composite ID format is enforced.
   */
  public function testCompositeIdFormat(): void {
    // Arrange
    WebhookFieldMapping::create([
      'id' => 'my_source.title',
      'label' => 'Title',
      'source_type' => 'my_source',
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    $loaded = WebhookFieldMapping::load('my_source.title');

    // Assert
    $this->assertNotNull($loaded);
    $this->assertSame('my_source.title', $loaded->id());
    $this->assertSame('my_source', $loaded->getSourceTypeId());
  }

  /**
   * Tests that a WebhookFieldMapping can be updated and reloaded.
   */
  public function testUpdateWebhookFieldMapping(): void {
    // Arrange
    WebhookFieldMapping::create([
      'id' => 'src.field_a',
      'label' => 'Original Label',
      'source_type' => 'src',
      'entity_field' => 'field_a',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.a'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $loaded */
    $loaded = WebhookFieldMapping::load('src.field_a');
    $loaded->set('label', 'Updated Label');
    $loaded->set('is_identifier', TRUE);
    $loaded->save();

    $reloaded = WebhookFieldMapping::load('src.field_a');

    // Assert
    $this->assertSame('Updated Label', $reloaded->label());
    $this->assertTrue($reloaded->isIdentifier());
  }

  /**
   * Tests that a WebhookFieldMapping can be deleted.
   */
  public function testDeleteWebhookFieldMapping(): void {
    // Arrange
    WebhookFieldMapping::create([
      'id' => 'src.field_to_delete',
      'label' => 'To Delete',
      'source_type' => 'src',
      'entity_field' => 'field_to_delete',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => [],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    $loaded = WebhookFieldMapping::load('src.field_to_delete');
    $this->assertNotNull($loaded);
    $loaded->delete();

    // Assert
    $this->assertNull(WebhookFieldMapping::load('src.field_to_delete'));
  }

  /**
   * Tests that toFieldMapping returns a valid FieldMapping value object.
   */
  public function testToFieldMappingReturnsValidValueObject(): void {
    // Arrange
    WebhookFieldMapping::create([
      'id' => 'src.field_price',
      'label' => 'Price',
      'source_type' => 'src',
      'entity_field' => 'field_price',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.price'],
      'mutation_plugin' => 'price_cents_to_decimal',
      'mutation_config' => ['precision' => 2],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $loaded */
    $loaded = WebhookFieldMapping::load('src.field_price');
    $fieldMapping = $loaded->toFieldMapping();

    // Assert
    $this->assertInstanceOf(FieldMapping::class, $fieldMapping);
    $this->assertSame('field_price', $fieldMapping->entityField);
    $this->assertFalse($fieldMapping->isIdentifier);
    $this->assertSame('json_path', $fieldMapping->resolver);
    $this->assertSame(['path' => '$.price'], $fieldMapping->resolverConfig);
    $this->assertSame('price_cents_to_decimal', $fieldMapping->mutationPlugin);
    $this->assertSame(['precision' => 2], $fieldMapping->mutationConfig);
  }

  /**
   * Tests that calculateDependencies declares a config dependency on the parent source type.
   */
  public function testCalculateDependenciesIncludesParentSourceType(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'parent_source',
      'label' => 'Parent Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'parent_source.field_x',
      'label' => 'Field X',
      'source_type' => 'parent_source',
      'entity_field' => 'field_x',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => [],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $loaded */
    $loaded = WebhookFieldMapping::load('parent_source.field_x');
    $dependencies = $loaded->getDependencies();

    // Assert
    $this->assertArrayHasKey('config', $dependencies);
    $this->assertContains(
      'entity_webhook.webhook_source_type.parent_source',
      $dependencies['config'],
    );
  }

  /**
   * Tests that deleting a WebhookSourceType cascade-deletes its field mappings.
   */
  public function testCascadeDeletionWhenSourceTypeIsDeleted(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'cascade_source',
      'label' => 'Cascade Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'cascade_source.field_one',
      'label' => 'Field One',
      'source_type' => 'cascade_source',
      'entity_field' => 'field_one',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => [],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'cascade_source.field_two',
      'label' => 'Field Two',
      'source_type' => 'cascade_source',
      'entity_field' => 'field_two',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => [],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    $this->assertNotNull(WebhookFieldMapping::load('cascade_source.field_one'));
    $this->assertNotNull(WebhookFieldMapping::load('cascade_source.field_two'));

    // Act
    $sourceType = WebhookSourceType::load('cascade_source');
    $sourceType->delete();

    // Assert
    $this->assertNull(WebhookFieldMapping::load('cascade_source.field_one'));
    $this->assertNull(WebhookFieldMapping::load('cascade_source.field_two'));
  }

  /**
   * Tests that getSourceType returns the parent entity when it exists.
   */
  public function testGetSourceTypeReturnsParentEntity(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'existing_source',
      'label' => 'Existing Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'existing_source.field_y',
      'label' => 'Field Y',
      'source_type' => 'existing_source',
      'entity_field' => 'field_y',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => [],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $loaded */
    $loaded = WebhookFieldMapping::load('existing_source.field_y');
    $sourceType = $loaded->getSourceType();

    // Assert
    $this->assertNotNull($sourceType);
    $this->assertSame('existing_source', $sourceType->id());
  }

  /**
   * Tests that getSourceType returns null when source type does not exist.
   */
  public function testGetSourceTypeReturnsNullWhenParentMissing(): void {
    // Arrange
    WebhookFieldMapping::create([
      'id' => 'orphan_source.field_z',
      'label' => 'Field Z',
      'source_type' => 'orphan_source',
      'entity_field' => 'field_z',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => [],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $loaded */
    $loaded = WebhookFieldMapping::load('orphan_source.field_z');
    $sourceType = $loaded->getSourceType();

    // Assert
    $this->assertNull($sourceType);
  }

}
