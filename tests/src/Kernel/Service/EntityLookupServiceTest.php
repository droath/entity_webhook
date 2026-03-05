<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Service;

use Drupal\entity_webhook\Service\EntityLookupServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Kernel tests for EntityLookupService.
 *
 * @group entity_webhook
 * @coversDefaultClass \Drupal\entity_webhook\Service\EntityLookupService
 */
class EntityLookupServiceTest extends KernelTestBase {

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
   * The entity lookup service under test.
   */
  private EntityLookupServiceInterface $lookupService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['field', 'node', 'filter']);

    NodeType::create([
      'type' => 'product',
      'name' => 'Product',
    ])->save();

    $this->lookupService = $this->container->get('entity_webhook.entity_lookup');
  }

  /**
   * Tests lookup by a single identifier field returns the matching entity.
   */
  public function testLookupBySingleIdentifierReturnsMatchingEntity(): void {
    $node = Node::create([
      'type' => 'product',
      'title' => 'Widget',
    ]);
    $node->save();

    $criteria = ['nid' => (string) $node->id()];
    $result = $this->lookupService->findEntity('node', $criteria);

    $this->assertNotNull($result);
    $this->assertSame((int) $node->id(), (int) $result->id());
  }

  /**
   * Tests lookup by composite identifiers returns only the exact match.
   */
  public function testLookupByCompositeIdentifiersReturnsExactMatch(): void {
    $nodeA = Node::create([
      'type' => 'product',
      'title' => 'Alpha',
    ]);
    $nodeA->save();

    $nodeB = Node::create([
      'type' => 'product',
      'title' => 'Beta',
    ]);
    $nodeB->save();

    $criteria = [
      'nid' => (string) $nodeA->id(),
      'type' => 'product',
    ];

    $result = $this->lookupService->findEntity('node', $criteria);

    $this->assertNotNull($result);
    $this->assertSame((int) $nodeA->id(), (int) $result->id());
  }

  /**
   * Tests that lookup returns null when no entity matches the criteria.
   */
  public function testLookupReturnsNullWhenNoEntityMatches(): void {
    $criteria = ['nid' => '99999'];
    $result = $this->lookupService->findEntity('node', $criteria);

    $this->assertNull($result);
  }

  /**
   * Tests that lookup with an empty criteria array returns null.
   */
  public function testLookupWithEmptyCriteriaReturnsNull(): void {
    $result = $this->lookupService->findEntity('node', []);

    $this->assertNull($result);
  }

}
