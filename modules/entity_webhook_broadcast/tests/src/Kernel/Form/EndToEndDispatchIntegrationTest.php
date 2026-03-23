<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Form;

use Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint;
use Drupal\entity_webhook_broadcast\Entity\OutboundFieldMapping;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscription;
use Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem;
use Drupal\entity_webhook_broadcast\Queue\OutboundQueueServiceInterface;
use Drupal\entity_webhook_broadcast\Service\OutboundDispatcherInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * End-to-end integration test for the outbound webhook dispatch pipeline.
 *
 * Creates an endpoint, subscription, and field mappings via config entities,
 * triggers an entity insert event through the dispatcher, and verifies that a
 * queue item is produced with the correct payload structure matching the field
 * mappings. This validates the complete pipeline from config → dispatch →
 * payload builder → queue without using form submission.
 *
 * @group entity_webhook_broadcast
 */
class EndToEndDispatchIntegrationTest extends KernelTestBase {

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
   * The outbound dispatcher service.
   */
  private OutboundDispatcherInterface $dispatcher;

  /**
   * The queue service for inspecting enqueued items.
   */
  private OutboundQueueServiceInterface $queueService;

  /**
   * The raw Drupal queue for claiming items.
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

    $this->dispatcher = $this->container->get('entity_webhook_broadcast.outbound_dispatcher');
    $this->queueService = $this->container->get('entity_webhook_broadcast.outbound_queue');
    $this->rawQueue = $this->container->get('queue')->get('entity_webhook_broadcast');
  }

  /**
   * Tests the full pipeline: config entities → entity insert → queue item with mapped payload.
   *
   * Verifies that all three tiers of config (endpoint → subscription → field
   * mappings) coordinate correctly through the dispatcher to produce a queue
   * item whose payload contains all mapped fields under their configured keys.
   */
  public function testEntityInsertProducesQueueItemWithPayloadMatchingFieldMappings(): void {
    // Arrange — create the three-tier config hierarchy.
    OutboundEndpoint::create([
      'id' => 'article_endpoint',
      'label' => 'Article Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => 'article',
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'article_sub',
      'label' => 'Article Subscription',
      'endpoint_id' => 'article_endpoint',
      'url' => 'https://receiver.example.com/webhook',
      'secret' => 'test-secret',
      'signing_algorithm' => 'sha256',
      'retry_max_attempts' => 5,
      'retry_base_delay' => 60,
      'active' => TRUE,
    ])->save();

    // Map the node title field to the 'node_title' output key.
    OutboundFieldMapping::create([
      'id' => 'article_sub.node_title',
      'label' => 'Node Title',
      'subscription_id' => 'article_sub',
      'entity_field' => 'title',
      'output_key' => 'node_title',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Map the node type (bundle) field to the 'content_type' output key.
    OutboundFieldMapping::create([
      'id' => 'article_sub.content_type',
      'label' => 'Content Type',
      'subscription_id' => 'article_sub',
      'entity_field' => 'type',
      'output_key' => 'content_type',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Integration Test Node']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    // Assert — exactly one item enqueued for the single active subscription.
    $this->assertSame($depthBefore + 1, $this->queueService->getQueueDepth());

    $queueItem = $this->rawQueue->claimItem();
    $this->assertNotFalse($queueItem, 'A queue item must have been enqueued.');

    $item = OutboundQueueItem::fromArray((array) $queueItem->data);

    // Verify item metadata.
    $this->assertSame('article_endpoint', $item->endpointId);
    $this->assertSame('article_sub', $item->subscriptionId);
    $this->assertSame('node', $item->entityTypeId);
    $this->assertSame((string) $node->id(), $item->entityId);
    $this->assertSame('insert', $item->event);
    $this->assertSame(1, $item->attempt);

    // Verify payload contains both mapped fields under their configured output keys.
    $this->assertArrayHasKey('node_title', $item->payload);
    $this->assertSame('Integration Test Node', $item->payload['node_title']);

    $this->assertArrayHasKey('content_type', $item->payload);
    $this->assertSame('article', $item->payload['content_type']);
  }

  /**
   * Tests that a non-matching bundle is not dispatched when endpoint filters by bundle.
   */
  public function testDispatcherSkipsEntityWhenBundleDoesNotMatchEndpointFilter(): void {
    // Arrange
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    OutboundEndpoint::create([
      'id' => 'article_only_endpoint',
      'label' => 'Article Only Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => 'article',
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'article_only_sub',
      'label' => 'Article Only Sub',
      'endpoint_id' => 'article_only_endpoint',
      'url' => 'https://example.com/article-only',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    $pageNode = Node::create(['type' => 'page', 'title' => 'A Page']);
    $pageNode->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act — dispatch a page node insert; endpoint only watches article.
    $this->dispatcher->dispatch($pageNode, 'insert');

    // Assert — no item enqueued for non-matching bundle.
    $this->assertSame($depthBefore, $this->queueService->getQueueDepth());
  }

  /**
   * Tests that update events are dispatched separately from insert events.
   */
  public function testUpdateEventIsDispatchedOnlyWhenEndpointWatchesUpdate(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'update_only_endpoint',
      'label' => 'Update Only Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['update'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'update_only_sub',
      'label' => 'Update Only Sub',
      'endpoint_id' => 'update_only_endpoint',
      'url' => 'https://example.com/updates',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Update Test Node']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act — insert should not trigger the update-only endpoint.
    $this->dispatcher->dispatch($node, 'insert');
    $this->assertSame($depthBefore, $this->queueService->getQueueDepth());

    // Act — update should trigger it.
    $this->dispatcher->dispatch($node, 'update');
    $this->assertSame($depthBefore + 1, $this->queueService->getQueueDepth());
  }

  /**
   * Tests that an endpoint with no field mappings produces an empty payload.
   */
  public function testEntityInsertWithNoFieldMappingsProducesEmptyPayload(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'empty_payload_endpoint',
      'label' => 'Empty Payload Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'empty_payload_sub',
      'label' => 'Empty Payload Sub',
      'endpoint_id' => 'empty_payload_endpoint',
      'url' => 'https://example.com/no-mappings',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    // No OutboundFieldMapping entities created for this subscription.
    $node = Node::create(['type' => 'article', 'title' => 'No Mappings']);
    $node->save();

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    $queueItem = $this->rawQueue->claimItem();
    $this->assertNotFalse($queueItem, 'A queue item must have been enqueued even with no mappings.');

    $item = OutboundQueueItem::fromArray((array) $queueItem->data);

    // Assert — payload is empty because there are no field mappings.
    $this->assertSame([], $item->payload);
  }

  /**
   * Tests that multiple subscriptions on the same endpoint each receive their own queue item.
   */
  public function testMultipleSubscriptionsEachReceiveIndependentQueueItems(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'multi_receiver_endpoint',
      'label' => 'Multi Receiver Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'receiver_alpha',
      'label' => 'Receiver Alpha',
      'endpoint_id' => 'multi_receiver_endpoint',
      'url' => 'https://alpha.example.com/hook',
      'secret' => 'secret-alpha',
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    OutboundSubscription::create([
      'id' => 'receiver_beta',
      'label' => 'Receiver Beta',
      'endpoint_id' => 'multi_receiver_endpoint',
      'url' => 'https://beta.example.com/hook',
      'secret' => NULL,
      'signing_algorithm' => 'none',
      'active' => TRUE,
    ])->save();

    OutboundFieldMapping::create([
      'id' => 'receiver_alpha.title',
      'label' => 'Alpha Title',
      'subscription_id' => 'receiver_alpha',
      'entity_field' => 'title',
      'output_key' => 'title',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Multi Receiver Test']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    // Assert — two subscriptions, two queue items.
    $this->assertSame($depthBefore + 2, $this->queueService->getQueueDepth());

    // Claim both items and verify each is addressed to a different subscription.
    $itemAlphaRaw = $this->rawQueue->claimItem();
    $itemBetaRaw = $this->rawQueue->claimItem();

    $this->assertNotFalse($itemAlphaRaw);
    $this->assertNotFalse($itemBetaRaw);

    $itemAlpha = OutboundQueueItem::fromArray((array) $itemAlphaRaw->data);
    $itemBeta = OutboundQueueItem::fromArray((array) $itemBetaRaw->data);

    $subscriptionIds = [$itemAlpha->subscriptionId, $itemBeta->subscriptionId];
    $this->assertContains('receiver_alpha', $subscriptionIds);
    $this->assertContains('receiver_beta', $subscriptionIds);
  }

}
