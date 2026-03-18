<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Plugin;

use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for WebhookQueueWorker plugin and WebhookProcessor service.
 *
 * @group entity_webhook
 */
class WebhookQueueWorkerTest extends KernelTestBase {

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

    WebhookEndpoint::create([
      'id' => 'test_endpoint',
      'label' => 'Test Endpoint',
      'target_entity_type' => 'node',
      'target_entity_bundle' => 'article',
      'source_types' => ['test_source'],
    ])->save();

    WebhookSourceType::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'field_mappings' => [
        [
          'entity_field' => 'type',
          'is_identifier' => FALSE,
          'resolver' => 'json_path',
          'resolver_config' => ['path' => '$.bundle'],
        ],
        [
          'entity_field' => 'title',
          'is_identifier' => FALSE,
          'resolver' => 'json_path',
          'resolver_config' => ['path' => '$.name'],
        ],
      ],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();
  }

  /**
   * Tests that processing a queue item creates a new entity.
   */
  public function testProcessItemCreatesNewEntity(): void {
    $item = new WebhookQueueItem(
      endpointId: 'test_endpoint',
      sourceType: 'test_source',
      payload: ['bundle' => 'article', 'name' => 'Created via Webhook'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $processor = $this->container->get('entity_webhook.webhook_processor');
    $processor->process($item);

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', 'Created via Webhook')
      ->execute();

    $this->assertCount(1, $nodes);
  }

  /**
   * Tests that processing a queue item with identifier updates existing entity.
   */
  public function testProcessItemUpdatesExistingEntity(): void {
    $existing = Node::create([
      'type' => 'article',
      'title' => 'Original Title',
    ]);
    $existing->save();

    WebhookSourceType::create([
      'id' => 'source_with_id',
      'label' => 'Source With ID',
      'field_mappings' => [
        [
          'entity_field' => 'nid',
          'is_identifier' => TRUE,
          'resolver' => 'json_path',
          'resolver_config' => ['path' => '$.id'],
        ],
        [
          'entity_field' => 'title',
          'is_identifier' => FALSE,
          'resolver' => 'json_path',
          'resolver_config' => ['path' => '$.name'],
        ],
      ],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookEndpoint::create([
      'id' => 'endpoint_with_id',
      'label' => 'Endpoint With ID',
      'target_entity_type' => 'node',
      'target_entity_bundle' => 'article',
      'source_types' => ['source_with_id'],
    ])->save();

    $item = new WebhookQueueItem(
      endpointId: 'endpoint_with_id',
      sourceType: 'source_with_id',
      payload: [
        'id' => (string) $existing->id(),
        'name' => 'Updated via Webhook',
      ],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $processor = $this->container->get('entity_webhook.webhook_processor');
    $processor->process($item);

    $reloaded = Node::load($existing->id());
    $this->assertSame('Updated via Webhook', $reloaded->label());
  }

  /**
   * Tests that queue worker plugin can be retrieved from plugin manager.
   */
  public function testQueueWorkerPluginIsDiscoverable(): void {
    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.queue_worker');
    $definition = $manager->getDefinition('entity_webhook_processor');

    $this->assertSame('entity_webhook_processor', $definition['id']);
  }

  /**
   * Tests that processing an item with unknown endpoint logs and skips.
   */
  public function testProcessItemWithUnknownEndpointSkipsGracefully(): void {
    $item = new WebhookQueueItem(
      endpointId: 'nonexistent_endpoint',
      sourceType: 'test_source',
      payload: ['name' => 'Should Not Be Created'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $processor = $this->container->get('entity_webhook.webhook_processor');

    // Should not throw — log and skip behavior.
    $processor->process($item);

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', 'Should Not Be Created')
      ->execute();

    $this->assertCount(0, $nodes);
  }

}
