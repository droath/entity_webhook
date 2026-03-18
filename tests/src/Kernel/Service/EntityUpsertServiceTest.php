<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Service;

use Drupal\entity_webhook\Entity\FieldMapping;
use Drupal\entity_webhook\Service\EntityUpsertServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for EntityUpsertService.
 *
 * @group entity_webhook
 * @coversDefaultClass \Drupal\entity_webhook\Service\EntityUpsertService
 */
class EntityUpsertServiceTest extends KernelTestBase {

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
   * The entity upsert service under test.
   */
  private EntityUpsertServiceInterface $upsertService;

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
      'type' => 'product',
      'name' => 'Product',
    ])->save();

    $this->upsertService = $this->container->get('entity_webhook.entity_upsert');
  }

  /**
   * Tests that upsert creates a new entity when none exists.
   */
  public function testUpsertCreatesNewEntityWhenNoneExists(): void {
    $mappings = [
      new FieldMapping(entityField: 'title', isIdentifier: FALSE, resolver: 'json_path', resolverConfig: ['path' => '$.name']),
    ];
    // extractedValues are post-JSONPath-extraction, keyed by entity field name.
    $extractedValues = ['title' => 'My New Product'];

    $entity = $this->upsertService->upsert('node', 'product', $mappings, $extractedValues);

    $this->assertNotNull($entity->id());
    $this->assertSame('My New Product', $entity->label());
    $this->assertTrue($entity->isNew() === FALSE);
  }

  /**
   * Tests that upsert updates an existing entity matched by identifier fields.
   */
  public function testUpsertUpdatesExistingEntityMatchedByIdentifier(): void {
    $existing = Node::create([
      'type' => 'product',
      'title' => 'Old Title',
    ]);
    $existing->save();
    $existingId = $existing->id();

    $mappings = [
      new FieldMapping(entityField: 'nid', isIdentifier: TRUE, resolver: 'json_path', resolverConfig: ['path' => '$.external_id']),
      new FieldMapping(entityField: 'title', isIdentifier: FALSE, resolver: 'json_path', resolverConfig: ['path' => '$.name']),
    ];
    // extractedValues are post-JSONPath-extraction, keyed by entity field name.
    $extractedValues = [
      'nid' => (string) $existingId,
      'title' => 'Updated Title',
    ];

    $entity = $this->upsertService->upsert('node', 'product', $mappings, $extractedValues);

    $this->assertSame((int) $existingId, (int) $entity->id());
    $this->assertSame('Updated Title', $entity->label());
  }

}
