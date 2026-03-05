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
      new FieldMapping('title', '$.name', isIdentifier: FALSE),
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
      new FieldMapping('nid', '$.external_id', isIdentifier: TRUE),
      new FieldMapping('title', '$.name', isIdentifier: FALSE),
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

  /**
   * Tests that upsert reports whether the entity was newly created.
   */
  public function testUpsertReportsWhetherEntityWasCreated(): void {
    $mappings = [
      new FieldMapping('title', '$.name', isIdentifier: FALSE),
    ];
    $extractedValues = ['title' => 'Brand New'];

    $this->upsertService->upsert('node', 'product', $mappings, $extractedValues);

    $this->assertTrue($this->upsertService->wasCreated());
  }

  /**
   * Tests that upsert reports false for wasCreated when entity already existed.
   */
  public function testUpsertReportsFalseForWasCreatedWhenUpdating(): void {
    $existing = Node::create([
      'type' => 'product',
      'title' => 'Existing Product',
    ]);
    $existing->save();

    $mappings = [
      new FieldMapping('nid', '$.id', isIdentifier: TRUE),
      new FieldMapping('title', '$.name', isIdentifier: FALSE),
    ];
    // extractedValues are post-JSONPath-extraction, keyed by entity field name.
    $extractedValues = [
      'nid' => (string) $existing->id(),
      'title' => 'Updated Name',
    ];

    $this->upsertService->upsert('node', 'product', $mappings, $extractedValues);

    $this->assertFalse($this->upsertService->wasCreated());
  }

}
