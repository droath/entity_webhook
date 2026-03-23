<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Service;

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
 * Kernel tests for OutboundDispatcher: dispatch pipeline end-to-end.
 *
 * Verifies that when an entity event is dispatched, the dispatcher loads
 * matching endpoints and subscriptions, builds payloads from field mappings,
 * and enqueues correctly populated OutboundQueueItem instances.
 *
 * @group entity_webhook_broadcast
 */
class OutboundDispatcherTest extends KernelTestBase {

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
   * The outbound queue service.
   */
  private OutboundQueueServiceInterface $queueService;

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
  }

  /**
   * Tests that dispatch enqueues an item when endpoint and subscription match.
   */
  public function testDispatchEnqueuesItemForMatchingEndpointAndSubscription(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'node_endpoint',
      'label' => 'Node Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'node_sub',
      'label' => 'Node Sub',
      'endpoint_id' => 'node_endpoint',
      'url' => 'https://example.com/webhook',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Hello']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    // Assert
    $this->assertSame($depthBefore + 1, $this->queueService->getQueueDepth());
  }

  /**
   * Tests that the enqueued item has the correct endpoint, subscription, entity, and event.
   */
  public function testEnqueuedItemContainsCorrectMetadata(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'meta_endpoint',
      'label' => 'Metadata Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['update'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'meta_sub',
      'label' => 'Metadata Sub',
      'endpoint_id' => 'meta_endpoint',
      'url' => 'https://example.com/meta',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Meta Test']);
    $node->save();

    // Intercept the queue item by using a spy on the queue service.
    /** @var \Drupal\Core\Queue\QueueInterface $rawQueue */
    $rawQueue = $this->container->get('queue')->get('entity_webhook_broadcast');
    $countBefore = $rawQueue->numberOfItems();

    // Act
    $this->dispatcher->dispatch($node, 'update');

    // Pull the enqueued item from the real queue.
    $this->assertSame($countBefore + 1, $rawQueue->numberOfItems());
    $queueItem = $rawQueue->claimItem();
    $this->assertNotFalse($queueItem, 'A queue item should have been enqueued.');

    $item = OutboundQueueItem::fromArray((array) $queueItem->data);

    // Assert metadata
    $this->assertSame('meta_endpoint', $item->endpointId);
    $this->assertSame('meta_sub', $item->subscriptionId);
    $this->assertSame('node', $item->entityTypeId);
    $this->assertSame((string) $node->id(), $item->entityId);
    $this->assertSame('update', $item->event);
    $this->assertSame(1, $item->attempt);
    $this->assertNull($item->deliveryLogId);
  }

  /**
   * Tests that the enqueued item payload contains mapped field values.
   */
  public function testEnqueuedItemPayloadContainsMappedFieldValues(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'payload_endpoint',
      'label' => 'Payload Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'payload_sub',
      'label' => 'Payload Sub',
      'endpoint_id' => 'payload_endpoint',
      'url' => 'https://example.com/payload',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    OutboundFieldMapping::create([
      'id' => 'payload_sub.title',
      'label' => 'Title Mapping',
      'subscription_id' => 'payload_sub',
      'entity_field' => 'title',
      'output_key' => 'title',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Payload Node']);
    $node->save();

    /** @var \Drupal\Core\Queue\QueueInterface $rawQueue */
    $rawQueue = $this->container->get('queue')->get('entity_webhook_broadcast');

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    // Pull the item and verify payload.
    $queueItem = $rawQueue->claimItem();
    $this->assertNotFalse($queueItem, 'A queue item should have been enqueued.');

    $item = OutboundQueueItem::fromArray((array) $queueItem->data);

    // Assert
    $this->assertArrayHasKey('title', $item->payload);
    $this->assertSame('Payload Node', $item->payload['title']);
  }

  /**
   * Tests that dispatch does not enqueue when the event does not match the endpoint.
   */
  public function testDispatchSkipsEndpointWhenEventDoesNotMatch(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'event_mismatch_endpoint',
      'label' => 'Event Mismatch Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['delete'],  // Only watches delete.
    ])->save();

    OutboundSubscription::create([
      'id' => 'event_mismatch_sub',
      'label' => 'Event Mismatch Sub',
      'endpoint_id' => 'event_mismatch_endpoint',
      'url' => 'https://example.com/delete-only',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Insert Attempt']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act: dispatch an insert event, but endpoint only watches delete.
    $this->dispatcher->dispatch($node, 'insert');

    // Assert: no item enqueued.
    $this->assertSame($depthBefore, $this->queueService->getQueueDepth());
  }

  /**
   * Tests that dispatch does not enqueue for a disabled endpoint.
   */
  public function testDispatchSkipsDisabledEndpoint(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'disabled_endpoint',
      'label' => 'Disabled Endpoint',
      'status' => FALSE,  // Disabled.
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'disabled_sub',
      'label' => 'Disabled Sub',
      'endpoint_id' => 'disabled_endpoint',
      'url' => 'https://example.com/disabled',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Disabled Check']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    // Assert
    $this->assertSame($depthBefore, $this->queueService->getQueueDepth());
  }

  /**
   * Tests that dispatch does not enqueue for an inactive subscription.
   */
  public function testDispatchSkipsInactiveSubscription(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'active_endpoint_inactive_sub',
      'label' => 'Active Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'inactive_sub',
      'label' => 'Inactive Sub',
      'endpoint_id' => 'active_endpoint_inactive_sub',
      'url' => 'https://example.com/inactive',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => FALSE,  // Inactive.
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Inactive Sub Check']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    // Assert
    $this->assertSame($depthBefore, $this->queueService->getQueueDepth());
  }

  /**
   * Tests that dispatch filters by entity bundle when endpoint specifies a bundle.
   */
  public function testDispatchSkipsEntityWhenBundleDoesNotMatchEndpointFilter(): void {
    // Arrange: endpoint watches only 'page' bundle.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    OutboundEndpoint::create([
      'id' => 'bundle_filter_endpoint',
      'label' => 'Bundle Filter Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => 'page',
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'bundle_filter_sub',
      'label' => 'Bundle Filter Sub',
      'endpoint_id' => 'bundle_filter_endpoint',
      'url' => 'https://example.com/page-only',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    // Article node — should NOT match the 'page'-only endpoint.
    $articleNode = Node::create(['type' => 'article', 'title' => 'Article Node']);
    $articleNode->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act
    $this->dispatcher->dispatch($articleNode, 'insert');

    // Assert: no item enqueued because bundle does not match.
    $this->assertSame($depthBefore, $this->queueService->getQueueDepth());
  }

  /**
   * Tests that dispatch enqueues one item per matching active subscription.
   */
  public function testDispatchEnqueuesOneItemPerActiveSubscription(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'multi_sub_endpoint',
      'label' => 'Multi Sub Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'multi_sub_alpha',
      'label' => 'Alpha',
      'endpoint_id' => 'multi_sub_endpoint',
      'url' => 'https://alpha.example.com/webhook',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    OutboundSubscription::create([
      'id' => 'multi_sub_beta',
      'label' => 'Beta',
      'endpoint_id' => 'multi_sub_endpoint',
      'url' => 'https://beta.example.com/webhook',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Multi Sub Node']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    // Assert: two subscriptions => two queue items.
    $this->assertSame($depthBefore + 2, $this->queueService->getQueueDepth());
  }

  /**
   * Tests that dispatch does not enqueue when no endpoints are configured.
   */
  public function testDispatchDoesNothingWhenNoEndpointsConfigured(): void {
    // Arrange: no endpoints saved.
    $node = Node::create(['type' => 'article', 'title' => 'No Endpoints']);
    $node->save();

    $depthBefore = $this->queueService->getQueueDepth();

    // Act
    $this->dispatcher->dispatch($node, 'insert');

    // Assert
    $this->assertSame($depthBefore, $this->queueService->getQueueDepth());
  }

}
