<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Entity;

use Drupal\entity_webhook\Entity\FieldMapping;
use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookFieldMapping;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Kernel integration tests for WebhookFieldMapping and WebhookSourceType.
 *
 * Verifies that WebhookSourceType::getFieldMappings() and
 * getIdentifierMappings() correctly load and adapt child WebhookFieldMapping
 * entities into FieldMapping value objects consumed by the processing
 * pipeline.
 *
 * @group entity_webhook
 */
class WebhookFieldMappingIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'node',
    'user',
    'field',
    'text',
    'filter',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'filter']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
  }

  /**
   * Tests that getFieldMappings returns FieldMapping objects from child entities.
   *
   * WebhookSourceType::getFieldMappings() must load all child
   * WebhookFieldMapping entities for the source type and return each one
   * adapted as a FieldMapping value object.
   */
  public function testGetFieldMappingsReturnsFieldMappingValueObjectsFromChildEntities(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'shopify_order',
      'label' => 'Shopify Order',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

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

    WebhookFieldMapping::create([
      'id' => 'shopify_order.title',
      'label' => 'Order Name',
      'source_type' => 'shopify_order',
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'shopify_order.field_price',
      'label' => 'Price',
      'source_type' => 'shopify_order',
      'entity_field' => 'field_price',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.total_price'],
      'mutation_plugin' => 'price_cents_to_decimal',
      'mutation_config' => ['precision' => 2],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $sourceType */
    $sourceType = WebhookSourceType::load('shopify_order');
    $mappings = $sourceType->getFieldMappings();

    // Assert
    $this->assertCount(3, $mappings);

    foreach ($mappings as $mapping) {
      $this->assertInstanceOf(FieldMapping::class, $mapping);
    }

    $byField = [];
    foreach ($mappings as $mapping) {
      $byField[$mapping->entityField] = $mapping;
    }

    $this->assertArrayHasKey('field_external_id', $byField);
    $this->assertArrayHasKey('title', $byField);
    $this->assertArrayHasKey('field_price', $byField);

    $this->assertTrue($byField['field_external_id']->isIdentifier);
    $this->assertSame('json_path', $byField['field_external_id']->resolver);
    $this->assertSame(['path' => '$.id'], $byField['field_external_id']->resolverConfig);

    $this->assertFalse($byField['title']->isIdentifier);
    $this->assertSame('', $byField['title']->mutationPlugin);

    $this->assertSame('price_cents_to_decimal', $byField['field_price']->mutationPlugin);
    $this->assertSame(['precision' => 2], $byField['field_price']->mutationConfig);
  }

  /**
   * Tests that getIdentifierMappings filters correctly from child entities.
   *
   * Only WebhookFieldMapping entities with is_identifier=TRUE must be
   * included in the returned array.
   */
  public function testGetIdentifierMappingsFiltersToOnlyIdentifierMappings(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'woo_product',
      'label' => 'WooCommerce Product',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'woo_product.field_sku',
      'label' => 'SKU',
      'source_type' => 'woo_product',
      'entity_field' => 'field_sku',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.sku'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'woo_product.title',
      'label' => 'Product Name',
      'source_type' => 'woo_product',
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'woo_product.field_external_id',
      'label' => 'External Product ID',
      'source_type' => 'woo_product',
      'entity_field' => 'field_external_id',
      'is_identifier' => TRUE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.id'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $sourceType */
    $sourceType = WebhookSourceType::load('woo_product');
    $identifiers = $sourceType->getIdentifierMappings();

    // Assert
    $this->assertCount(2, $identifiers);

    foreach ($identifiers as $identifier) {
      $this->assertInstanceOf(FieldMapping::class, $identifier);
      $this->assertTrue($identifier->isIdentifier, "Non-identifier mapping slipped through: '{$identifier->entityField}'");
    }

    $identifierFields = array_map(static fn (FieldMapping $m) => $m->entityField, $identifiers);
    $this->assertContains('field_sku', $identifierFields);
    $this->assertContains('field_external_id', $identifierFields);
    $this->assertNotContains('title', $identifierFields);
  }

  /**
   * Tests that mappings from different source types do not cross-contaminate.
   *
   * Each source type must only see its own child WebhookFieldMapping entities.
   */
  public function testGetFieldMappingsIsolatesChildEntitiesBySourceType(): void {
    // Arrange
    WebhookSourceType::create([
      'id' => 'source_a',
      'label' => 'Source A',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookSourceType::create([
      'id' => 'source_b',
      'label' => 'Source B',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'source_a.title',
      'label' => 'Source A Title',
      'source_type' => 'source_a',
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name_a'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'source_b.title',
      'label' => 'Source B Title',
      'source_type' => 'source_b',
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name_b'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $sourceA */
    $sourceA = WebhookSourceType::load('source_a');
    $mappingsA = $sourceA->getFieldMappings();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $sourceB */
    $sourceB = WebhookSourceType::load('source_b');
    $mappingsB = $sourceB->getFieldMappings();

    // Assert
    $this->assertCount(1, $mappingsA, 'Source A should have exactly one field mapping.');
    $this->assertCount(1, $mappingsB, 'Source B should have exactly one field mapping.');

    $this->assertSame(['path' => '$.name_a'], $mappingsA[array_key_first($mappingsA)]->resolverConfig);
    $this->assertSame(['path' => '$.name_b'], $mappingsB[array_key_first($mappingsB)]->resolverConfig);
  }

  /**
   * Tests the end-to-end webhook processing pipeline with child field mapping entities.
   *
   * The processing pipeline (WebhookProcessor -> EntityUpsertService) consumes
   * FieldMapping value objects via getFieldMappings(). This test verifies the
   * full adapter chain works: WebhookFieldMapping entities -> toFieldMapping()
   * -> FieldMapping value object -> pipeline.
   */
  public function testProcessingPipelineWorksWithChildFieldMappingEntities(): void {
    // Arrange
    WebhookEndpoint::create([
      'id' => 'integration_endpoint',
      'label' => 'Integration Endpoint',
      'target_entity_type' => 'node',
      'target_entity_bundle' => 'article',
      'source_types' => ['integration_source'],
    ])->save();

    WebhookSourceType::create([
      'id' => 'integration_source',
      'label' => 'Integration Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'integration_source.type',
      'label' => 'Bundle',
      'source_type' => 'integration_source',
      'entity_field' => 'type',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.bundle'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    WebhookFieldMapping::create([
      'id' => 'integration_source.title',
      'label' => 'Title',
      'source_type' => 'integration_source',
      'entity_field' => 'title',
      'is_identifier' => FALSE,
      'resolver' => 'json_path',
      'resolver_config' => ['path' => '$.name'],
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    $payload = ['bundle' => 'article', 'name' => 'Integration Test Node'];

    // Act
    /** @var \Drupal\entity_webhook\Service\WebhookProcessorInterface $processor */
    $processor = $this->container->get('entity_webhook.webhook_processor');

    $queueItem = new \Drupal\entity_webhook\Queue\WebhookQueueItem(
      endpointId: 'integration_endpoint',
      sourceType: 'integration_source',
      payload: $payload,
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $processor->process($queueItem);

    // Assert: node was created with the mapped title
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', 'Integration Test Node')
      ->execute();

    $this->assertCount(1, $nodes, 'Processing pipeline should have created exactly one node.');
  }

}
