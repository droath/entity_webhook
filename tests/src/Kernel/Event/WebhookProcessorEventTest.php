<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Event;

use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\entity_webhook\Event\EntityWebhookEvents;
use Drupal\entity_webhook\Event\EntityWebhookPostSaveEvent;
use Drupal\entity_webhook\Event\EntityWebhookPreSaveEvent;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Kernel tests verifying event dispatching in WebhookProcessor.
 *
 * @group entity_webhook
 */
class WebhookProcessorEventTest extends KernelTestBase {

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
   * Tests that PreSave and PostSave events are dispatched during processing.
   */
  public function testPreSaveAndPostSaveEventsAreDispatched(): void {
    $recorder = new EventRecorder();

    $subscriber = new class($recorder) implements EventSubscriberInterface {
      public function __construct(private readonly EventRecorder $recorder) {}

      public static function getSubscribedEvents(): array {
        return [
          EntityWebhookEvents::PRE_SAVE => 'onPreSave',
          EntityWebhookEvents::POST_SAVE => 'onPostSave',
        ];
      }

      public function onPreSave(EntityWebhookPreSaveEvent $event): void {
        $this->recorder->preSaveEvent = $event;
      }

      public function onPostSave(EntityWebhookPostSaveEvent $event): void {
        $this->recorder->postSaveEvent = $event;
      }
    };

    $this->container->get('event_dispatcher')->addSubscriber($subscriber);

    $item = new WebhookQueueItem(
      endpointId: 'test_endpoint',
      sourceType: 'test_source',
      payload: ['bundle' => 'article', 'name' => 'Event Test Node'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $this->container->get('entity_webhook.webhook_processor')->process($item);

    $this->assertNotNull($recorder->preSaveEvent, 'PreSave event was not dispatched.');
    $this->assertNotNull($recorder->postSaveEvent, 'PostSave event was not dispatched.');

    $this->assertSame('test_endpoint', $recorder->preSaveEvent->endpointId);
    $this->assertSame('test_source', $recorder->preSaveEvent->sourceType);
    $this->assertTrue($recorder->preSaveEvent->isNew);

    $this->assertSame('test_endpoint', $recorder->postSaveEvent->endpointId);
    $this->assertSame('test_source', $recorder->postSaveEvent->sourceType);
    $this->assertTrue($recorder->postSaveEvent->wasCreated);
  }

  /**
   * Tests that aborting in PreSave prevents the entity save and PostSave event.
   */
  public function testAbortingInPreSavePreventsEntitySaveAndPostSave(): void {
    $recorder = new EventRecorder();

    $subscriber = new class($recorder) implements EventSubscriberInterface {
      public function __construct(private readonly EventRecorder $recorder) {}

      public static function getSubscribedEvents(): array {
        return [
          EntityWebhookEvents::PRE_SAVE => 'onPreSave',
          EntityWebhookEvents::POST_SAVE => 'onPostSave',
        ];
      }

      public function onPreSave(EntityWebhookPreSaveEvent $event): void {
        $event->abort();
      }

      public function onPostSave(EntityWebhookPostSaveEvent $event): void {
        $this->recorder->postSaveEvent = $event;
      }
    };

    $this->container->get('event_dispatcher')->addSubscriber($subscriber);

    $item = new WebhookQueueItem(
      endpointId: 'test_endpoint',
      sourceType: 'test_source',
      payload: ['bundle' => 'article', 'name' => 'Should Not Be Saved'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $this->container->get('entity_webhook.webhook_processor')->process($item);

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', 'Should Not Be Saved')
      ->execute();

    $this->assertCount(0, $nodes, 'Entity was saved despite abort.');
    $this->assertNull($recorder->postSaveEvent, 'PostSave event fired despite abort.');
  }

  /**
   * Tests that PostSave event carries wasCreated=FALSE when updating.
   */
  public function testPostSaveEventReflectsUpdateOperation(): void {
    $recorder = new EventRecorder();

    $subscriber = new class($recorder) implements EventSubscriberInterface {
      public function __construct(private readonly EventRecorder $recorder) {}

      public static function getSubscribedEvents(): array {
        return [EntityWebhookEvents::POST_SAVE => 'onPostSave'];
      }

      public function onPostSave(EntityWebhookPostSaveEvent $event): void {
        $this->recorder->postSaveEvent = $event;
      }
    };

    $this->container->get('event_dispatcher')->addSubscriber($subscriber);

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

    $existing = Node::create([
      'type' => 'article',
      'title' => 'Original Title',
    ]);
    $existing->save();

    $item = new WebhookQueueItem(
      endpointId: 'endpoint_with_id',
      sourceType: 'source_with_id',
      payload: ['id' => (string) $existing->id(), 'name' => 'Updated Title'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $this->container->get('entity_webhook.webhook_processor')->process($item);

    $this->assertNotNull($recorder->postSaveEvent);
    $this->assertFalse($recorder->postSaveEvent->wasCreated, 'wasCreated should be FALSE for an update.');
  }

}

/**
 * Simple recording object for capturing dispatched events in tests.
 */
class EventRecorder {

  /**
   * The captured PreSave event, or NULL if not dispatched.
   */
  public ?EntityWebhookPreSaveEvent $preSaveEvent = NULL;

  /**
   * The captured PostSave event, or NULL if not dispatched.
   */
  public ?EntityWebhookPostSaveEvent $postSaveEvent = NULL;

}
