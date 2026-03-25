<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Service;

use Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscription;
use Drupal\entity_webhook_broadcast\Service\OutboundDispatcherInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for OutboundDispatcher condition evaluation.
 *
 * Verifies condition evaluation behaviour in the integrated dispatch pipeline
 * using the entity_bundle:node condition.
 *
 * @group entity_webhook_broadcast
 */
class OutboundDispatcherConditionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'entity_webhook_broadcast',
    'node',
    'user',
    'field',
    'text',
    'filter',
    'system',
  ];

  /**
   * The dispatcher service under test.
   */
  private OutboundDispatcherInterface $dispatcher;

  /**
   * The raw Drupal queue used to count enqueued items.
   */
  private \Drupal\Core\Queue\QueueInterface $rawQueue;

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

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    $this->dispatcher = $this->container->get('entity_webhook_broadcast.outbound_dispatcher');
    $this->rawQueue = $this->container->get('queue')->get('entity_webhook_broadcast');
  }

  /**
   * Returns the number of items currently in the outbound queue.
   */
  private function queueDepth(): int {
    return $this->rawQueue->numberOfItems();
  }

  /**
   * Creates a minimal active endpoint+subscription pair watching node insert events.
   *
   * @param string $endpointId
   *   The machine name for the endpoint.
   * @param array<string, mixed> $conditions
   *   Raw condition plugin configuration keyed by condition plugin instance ID.
   */
  private function createEndpointWithSubscription(
    string $endpointId,
    array $conditions = [],
  ): void {
    OutboundEndpoint::create([
      'id' => $endpointId,
      'label' => $endpointId,
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
      'conditions' => $conditions,
    ])->save();

    OutboundSubscription::create([
      'id' => $endpointId . '_sub',
      'label' => $endpointId . ' sub',
      'endpoint_id' => $endpointId,
      'url' => 'https://example.com/' . $endpointId,
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();
  }

  /**
   * Tests backward compatibility: endpoint without conditions dispatches for any node.
   */
  public function testEndpointWithNoConditionsDispatchesForAnyNode(): void {
    $this->createEndpointWithSubscription('no_cond_ep');

    $articleNode = Node::create(['type' => 'article', 'title' => 'Article Node', 'status' => 1]);
    $articleNode->save();

    $pageNode = Node::create(['type' => 'page', 'title' => 'Page Node', 'status' => 0]);
    $pageNode->save();

    $before = $this->queueDepth();
    $this->dispatcher->dispatch($articleNode, 'insert');
    $this->assertSame($before + 1, $this->queueDepth(), 'Article node must dispatch with no conditions.');

    $before = $this->queueDepth();
    $this->dispatcher->dispatch($pageNode, 'insert');
    $this->assertSame($before + 1, $this->queueDepth(), 'Page node must dispatch with no conditions.');
  }

  /**
   * Tests that a bundle condition with empty bundles config is stripped by preSave.
   *
   * entity_bundle:node with empty bundles matches the defaultConfiguration()
   * and is stripped by ConditionPluginCollection during preSave. The endpoint
   * behaves as unconfigured and dispatches for all nodes.
   */
  public function testEndpointWithDefaultConditionBehavesAsUnconfigured(): void {
    $this->createEndpointWithSubscription('default_cond_ep', [
      'entity_bundle:node' => [
        'id' => 'entity_bundle:node',
        'negate' => FALSE,
        'bundles' => [],
      ],
    ]);

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $endpoint */
    $endpoint = OutboundEndpoint::load('default_cond_ep');
    $this->assertSame(
      0,
      $endpoint->getConditions()->count(),
      'entity_bundle:node with empty bundles matches defaultConfiguration() and is stripped by preSave().',
    );
    $this->assertSame(
      [],
      $endpoint->getActiveConditions(),
      'getActiveConditions() must return empty array when condition config was stripped.',
    );

    $node = Node::create(['type' => 'article', 'title' => 'Article Node', 'status' => 1]);
    $node->save();

    $before = $this->queueDepth();
    $this->dispatcher->dispatch($node, 'insert');
    $this->assertSame(
      $before + 1,
      $this->queueDepth(),
      'Endpoint with stripped condition dispatches like an unconditional endpoint.',
    );
  }

  /**
   * Tests that a bundle condition blocks dispatch for non-matching bundles.
   *
   * An endpoint with entity_bundle:node configured for 'article' must block
   * dispatch for 'page' nodes.
   */
  public function testEndpointWithBundleConditionBlocksNonMatchingBundle(): void {
    $this->createEndpointWithSubscription('bundle_cond_ep', [
      'entity_bundle:node' => [
        'id' => 'entity_bundle:node',
        'negate' => FALSE,
        'bundles' => ['article' => 'article'],
      ],
    ]);

    $pageNode = Node::create(['type' => 'page', 'title' => 'Page Node', 'status' => 1]);
    $pageNode->save();

    $before = $this->queueDepth();
    $this->dispatcher->dispatch($pageNode, 'insert');
    $this->assertSame(
      $before,
      $this->queueDepth(),
      'Page node must NOT dispatch when bundle condition is configured for article only.',
    );
  }

  /**
   * Tests that a bundle condition allows dispatch for matching bundles.
   *
   * An endpoint with entity_bundle:node configured for 'article' must allow
   * dispatch for 'article' nodes.
   */
  public function testEndpointWithBundleConditionAllowsMatchingBundle(): void {
    $this->createEndpointWithSubscription('bundle_match_ep', [
      'entity_bundle:node' => [
        'id' => 'entity_bundle:node',
        'negate' => FALSE,
        'bundles' => ['article' => 'article'],
      ],
    ]);

    $articleNode = Node::create(['type' => 'article', 'title' => 'Article Node', 'status' => 1]);
    $articleNode->save();

    $before = $this->queueDepth();
    $this->dispatcher->dispatch($articleNode, 'insert');
    $this->assertSame(
      $before + 1,
      $this->queueDepth(),
      'Article node must dispatch when bundle condition is configured for article.',
    );
  }

  /**
   * Tests negated bundle condition: blocks matching bundles, allows others.
   *
   * An endpoint with entity_bundle:node negated for 'article' means "dispatch
   * for everything EXCEPT article". Article nodes should be blocked, page
   * nodes should dispatch.
   */
  public function testEndpointWithNegatedBundleCondition(): void {
    $this->createEndpointWithSubscription('negated_bundle_ep', [
      'entity_bundle:node' => [
        'id' => 'entity_bundle:node',
        'negate' => TRUE,
        'bundles' => ['article' => 'article'],
      ],
    ]);

    $articleNode = Node::create(['type' => 'article', 'title' => 'Article Node', 'status' => 1]);
    $articleNode->save();

    $pageNode = Node::create(['type' => 'page', 'title' => 'Page Node', 'status' => 1]);
    $pageNode->save();

    $before = $this->queueDepth();
    $this->dispatcher->dispatch($articleNode, 'insert');
    $this->assertSame(
      $before,
      $this->queueDepth(),
      'Article node must NOT dispatch with negated bundle condition for article.',
    );

    $before = $this->queueDepth();
    $this->dispatcher->dispatch($pageNode, 'insert');
    $this->assertSame(
      $before + 1,
      $this->queueDepth(),
      'Page node must dispatch with negated bundle condition for article.',
    );
  }

}
