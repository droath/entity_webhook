<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Entity;

use Drupal\entity_webhook\Entity\WebhookFieldMapping;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for WebhookSourceType config entity CRUD operations.
 *
 * @group entity_webhook
 */
class WebhookSourceTypeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook'];

  /**
   * Tests that a WebhookSourceType can be created and loaded.
   */
  public function testCreateAndLoadWebhookSourceType(): void {
    WebhookSourceType::create([
      'id' => 'shopify_order',
      'label' => 'Shopify Order',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    $loaded = WebhookSourceType::load('shopify_order');

    $this->assertInstanceOf(WebhookSourceTypeInterface::class, $loaded);
    $this->assertSame('shopify_order', $loaded->id());
    $this->assertSame('Shopify Order', $loaded->label());
  }

  /**
   * Tests that getFieldMappings returns FieldMapping objects from child entities.
   *
   * Each WebhookFieldMapping config entity belonging to a source type is
   * returned as a FieldMapping value object by getFieldMappings(). This test
   * verifies the child-entity-to-value-object adapter chain end-to-end.
   */
  public function testFieldMappingsReturnedFromChildEntities(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'test_source.field_external_id',
      'label' => 'External ID',
      'source_type' => 'test_source',
      'entity_field' => 'field_external_id',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.id'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'test_source.title',
      'label' => 'Title',
      'source_type' => 'test_source',
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('test_source');
    $mappings = $loaded->getFieldMappings();

    // Assert
    $this->assertCount(2, $mappings);

    $fieldNames = array_map(static fn ($m) => $m->entityField, $mappings);
    $this->assertContains('field_external_id', $fieldNames);
    $this->assertContains('title', $fieldNames);

    $identifierMapping = array_values(array_filter($mappings, static fn ($m) => $m->entityField === 'field_external_id'))[0];
    $this->assertTrue($identifierMapping->isIdentifier);

    $titleMapping = array_values(array_filter($mappings, static fn ($m) => $m->entityField === 'title'))[0];
    $this->assertFalse($titleMapping->isIdentifier);
  }

  /**
   * Tests that getIdentifierMappings returns only identifier-flagged mappings.
   */
  public function testGetIdentifierMappingsReturnsOnlyIdentifiers(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'source_with_identifiers',
      'label' => 'Source With Identifiers',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'source_with_identifiers.field_external_id',
      'label' => 'External ID',
      'source_type' => 'source_with_identifiers',
      'entity_field' => 'field_external_id',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.id'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'source_with_identifiers.title',
      'label' => 'Title',
      'source_type' => 'source_with_identifiers',
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'source_with_identifiers.field_sku',
      'label' => 'SKU',
      'source_type' => 'source_with_identifiers',
      'entity_field' => 'field_sku',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.sku'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('source_with_identifiers');
    $identifiers = $loaded->getIdentifierMappings();

    // Assert
    $this->assertCount(2, $identifiers);

    $identifierFields = array_map(static fn ($m) => $m->entityField, $identifiers);
    $this->assertContains('field_external_id', $identifierFields);
    $this->assertContains('field_sku', $identifierFields);

    foreach ($identifiers as $identifier) {
      $this->assertTrue($identifier->isIdentifier, "Mapping for '{$identifier->entityField}' should be an identifier.");
    }
  }

  /**
   * Tests that getFieldMappings returns an empty array when no child entities exist.
   */
  public function testGetFieldMappingsReturnsEmptyArrayWhenNoChildEntities(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'empty_source',
      'label' => 'Empty Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('empty_source');
    $mappings = $loaded->getFieldMappings();

    // Assert
    $this->assertSame([], $mappings);
  }

  /**
   * Tests that a WebhookSourceType can be updated.
   */
  public function testUpdateWebhookSourceType(): void {
    WebhookSourceType::create([
      'id' => 'updatable_source',
      'label' => 'Original Label',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('updatable_source');
    $loaded->set('label', 'Updated Label');
    $loaded->set('verification_plugin', 'hmac');
    $loaded->save();

    $reloaded = WebhookSourceType::load('updatable_source');
    $this->assertSame('Updated Label', $reloaded->label());
    $this->assertSame('hmac', $reloaded->getVerificationPlugin());
  }

  /**
   * Tests that a WebhookSourceType can be deleted.
   */
  public function testDeleteWebhookSourceType(): void {
    WebhookSourceType::create([
      'id' => 'deletable_source',
      'label' => 'Deletable Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    $loaded = WebhookSourceType::load('deletable_source');
    $this->assertNotNull($loaded);

    $loaded->delete();

    $this->assertNull(WebhookSourceType::load('deletable_source'));
  }

  /**
   * Tests that the endpoint property is stored and retrieved via getEndpointId().
   */
  public function testEndpointIdStoredAndRetrievedViaGetter(): void {
    WebhookSourceType::create([
      'id' => 'endpoint_source',
      'label' => 'Endpoint Source',
      'verification_plugin' => '',
      'verification_config' => [],
      'endpoint' => 'my_endpoint',
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('endpoint_source');

    $this->assertSame('my_endpoint', $loaded->getEndpointId());
  }

  /**
   * Tests that getEndpointId returns empty string when endpoint is not set.
   */
  public function testGetEndpointIdDefaultsToEmptyString(): void {
    WebhookSourceType::create([
      'id' => 'no_endpoint_source',
      'label' => 'No Endpoint Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('no_endpoint_source');

    $this->assertSame('', $loaded->getEndpointId());
  }

}
