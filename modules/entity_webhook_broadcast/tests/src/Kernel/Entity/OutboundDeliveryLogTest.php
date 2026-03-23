<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Entity;

use Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLog;
use Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for OutboundDeliveryLog content entity CRUD and status transitions.
 *
 * Verifies that the entity can be created, loaded, updated, and deleted, that
 * all field getters return the persisted values, and that status transitions
 * between pending, success, failed, and abandoned are correctly stored.
 *
 * @group entity_webhook_broadcast
 */
class OutboundDeliveryLogTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook', 'entity_webhook_broadcast', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('outbound_delivery_log');
    $this->installSchema('system', ['sequences']);
  }

  // ---------------------------------------------------------------------------
  // CRUD operations
  // ---------------------------------------------------------------------------

  /**
   * Tests that an OutboundDeliveryLog can be created and loaded with all fields.
   */
  public function testCreateAndLoadDeliveryLogWithAllFields(): void {
    // Arrange
    $nextRetryAt = time() + 60;

    // Act
    OutboundDeliveryLog::create([
      'subscription' => 'my_sub',
      'entity_type' => 'node',
      'entity_id' => '42',
      'event' => 'insert',
      'payload_hash' => str_repeat('a', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
      'http_status' => NULL,
      'response_body' => NULL,
      'next_retry_at' => $nextRetryAt,
    ])->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $this->assertCount(1, $ids);

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = $storage->load(reset($ids));

    // Assert
    $this->assertInstanceOf(OutboundDeliveryLogInterface::class, $log);
    $this->assertSame('my_sub', $log->getSubscriptionId());
    $this->assertSame('node', $log->getSourceEntityType());
    $this->assertSame('42', $log->getSourceEntityId());
    $this->assertSame('insert', $log->getEvent());
    $this->assertSame(str_repeat('a', 64), $log->getPayloadHash());
    $this->assertSame(1, $log->getAttempt());
    $this->assertSame(5, $log->getMaxAttempts());
    $this->assertSame('pending', $log->getStatus());
    $this->assertNull($log->getHttpStatus());
    $this->assertNull($log->getResponseBody());
    $this->assertSame($nextRetryAt, $log->getNextRetryAt());
  }

  /**
   * Tests that a delivery log can be updated and changes are persisted.
   */
  public function testUpdateDeliveryLogPersistsChanges(): void {
    // Arrange
    OutboundDeliveryLog::create([
      'subscription' => 'update_sub',
      'entity_type' => 'node',
      'entity_id' => '1',
      'event' => 'update',
      'payload_hash' => str_repeat('b', 64),
      'attempt' => 1,
      'max_attempts' => 3,
      'status' => 'pending',
    ])->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = $storage->load(reset($ids));

    // Act
    $log->setStatus('success');
    $log->setHttpStatus(200);
    $log->setResponseBody('{"ok":true}');
    $log->save();

    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($log->id());

    // Assert
    $this->assertSame('success', $reloaded->getStatus());
    $this->assertSame(200, $reloaded->getHttpStatus());
    $this->assertSame('{"ok":true}', $reloaded->getResponseBody());
  }

  /**
   * Tests that a delivery log can be deleted.
   */
  public function testDeleteDeliveryLog(): void {
    // Arrange
    OutboundDeliveryLog::create([
      'subscription' => 'delete_sub',
      'entity_type' => 'node',
      'entity_id' => '99',
      'event' => 'delete',
      'payload_hash' => str_repeat('c', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
    ])->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $id = reset($ids);
    $this->assertNotNull($storage->load($id));

    // Act
    $storage->load($id)->delete();

    $storage->resetCache();

    // Assert
    $this->assertNull($storage->load($id));
  }

  // ---------------------------------------------------------------------------
  // Status transitions
  // ---------------------------------------------------------------------------

  /**
   * Tests the pending to success status transition.
   */
  public function testStatusTransitionFromPendingToSuccess(): void {
    // Arrange
    OutboundDeliveryLog::create([
      'subscription' => 'success_sub',
      'entity_type' => 'node',
      'entity_id' => '10',
      'event' => 'insert',
      'payload_hash' => str_repeat('d', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
    ])->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = $storage->load(reset($ids));
    $this->assertSame('pending', $log->getStatus());

    // Act
    $log->setStatus('success')
      ->setHttpStatus(200)
      ->save();

    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($log->id());

    // Assert
    $this->assertSame('success', $reloaded->getStatus());
    $this->assertSame(200, $reloaded->getHttpStatus());
  }

  /**
   * Tests the pending to failed status transition.
   */
  public function testStatusTransitionFromPendingToFailed(): void {
    // Arrange
    OutboundDeliveryLog::create([
      'subscription' => 'failed_sub',
      'entity_type' => 'node',
      'entity_id' => '11',
      'event' => 'update',
      'payload_hash' => str_repeat('e', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
    ])->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = $storage->load(reset($ids));

    // Act: mark as failed with a server error HTTP status
    $httpErrorStatus = 503;
    $log->setStatus('failed')
      ->setHttpStatus($httpErrorStatus)
      ->setResponseBody('Service Unavailable')
      ->save();

    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($log->id());

    // Assert
    $this->assertSame('failed', $reloaded->getStatus());
    $this->assertSame($httpErrorStatus, $reloaded->getHttpStatus());
    $this->assertSame('Service Unavailable', $reloaded->getResponseBody());
  }

  /**
   * Tests the full pending to failed to abandoned status transition.
   *
   * Simulates exhausting all retry attempts: status starts as pending,
   * moves to failed when an attempt fails, then to abandoned when max
   * attempts are reached.
   */
  public function testStatusTransitionFromPendingToFailedToAbandoned(): void {
    // Arrange: create a log entry at its last attempt
    $maxAttempts = 3;
    OutboundDeliveryLog::create([
      'subscription' => 'abandoned_sub',
      'entity_type' => 'node',
      'entity_id' => '12',
      'event' => 'insert',
      'payload_hash' => str_repeat('f', 64),
      'attempt' => $maxAttempts,
      'max_attempts' => $maxAttempts,
      'status' => 'pending',
    ])->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = $storage->load(reset($ids));
    $this->assertSame('pending', $log->getStatus());

    // Act step 1: attempt fails, move to failed
    $log->setStatus('failed')
      ->setHttpStatus(500)
      ->save();

    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $afterFailed */
    $afterFailed = $storage->load($log->id());
    $this->assertSame('failed', $afterFailed->getStatus());

    // Act step 2: max attempts reached, abandon
    $afterFailed->setStatus('abandoned')->save();

    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $abandoned */
    $abandoned = $storage->load($log->id());

    // Assert
    $this->assertSame('abandoned', $abandoned->getStatus());
    $this->assertSame($maxAttempts, $abandoned->getAttempt());
  }

  // ---------------------------------------------------------------------------
  // incrementAttempt() and setNextRetryAt()
  // ---------------------------------------------------------------------------

  /**
   * Tests that incrementAttempt increases the attempt counter by one.
   */
  public function testIncrementAttemptIncreasesCounterByOne(): void {
    // Arrange
    OutboundDeliveryLog::create([
      'subscription' => 'inc_sub',
      'entity_type' => 'node',
      'entity_id' => '20',
      'event' => 'insert',
      'payload_hash' => str_repeat('g', 64),
      'attempt' => 2,
      'max_attempts' => 5,
      'status' => 'pending',
    ])->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = $storage->load(reset($ids));

    // Act
    $log->incrementAttempt()->save();

    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($log->id());

    // Assert
    $this->assertSame(3, $reloaded->getAttempt());
  }

  /**
   * Tests that setNextRetryAt stores and clears the retry timestamp correctly.
   */
  public function testSetNextRetryAtStoresAndClearsTimestamp(): void {
    // Arrange
    $retryTimestamp = time() + 120;
    OutboundDeliveryLog::create([
      'subscription' => 'retry_ts_sub',
      'entity_type' => 'node',
      'entity_id' => '30',
      'event' => 'update',
      'payload_hash' => str_repeat('h', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => $retryTimestamp,
    ])->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = $storage->load(reset($ids));
    $this->assertSame($retryTimestamp, $log->getNextRetryAt());

    // Act: clear the timestamp
    $log->setNextRetryAt(NULL)->save();

    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($log->id());

    // Assert
    $this->assertNull($reloaded->getNextRetryAt());
  }

  // ---------------------------------------------------------------------------
  // payload field roundtrip
  // ---------------------------------------------------------------------------

  /**
   * Tests that setPayload and getPayload roundtrip the JSON-encoded array.
   */
  public function testPayloadRoundtripSerializesAndDeserializesCorrectly(): void {
    // Arrange
    $payload = ['title' => 'Test Node', 'status' => TRUE, 'tags' => [1, 2, 3]];

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = OutboundDeliveryLog::create([
      'subscription' => 'payload_sub',
      'entity_type' => 'node',
      'entity_id' => '50',
      'event' => 'insert',
      'payload_hash' => str_repeat('i', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
    ]);
    $log->setPayload($payload);
    $log->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($log->id());

    // Assert
    $this->assertSame($payload, $reloaded->getPayload());
  }

  /**
   * Tests that getPayload returns an empty array when no payload is stored.
   */
  public function testGetPayloadReturnsEmptyArrayWhenNotSet(): void {
    // Arrange + Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = OutboundDeliveryLog::create([
      'subscription' => 'no_payload_sub',
      'entity_type' => 'node',
      'entity_id' => '51',
      'event' => 'delete',
      'payload_hash' => str_repeat('j', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
    ]);
    $log->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($log->id());

    // Assert
    $this->assertSame([], $reloaded->getPayload());
  }

  // ---------------------------------------------------------------------------
  // Views data handler
  // ---------------------------------------------------------------------------

  /**
   * Tests that the Views data handler exposes outbound_delivery_log table data.
   *
   * Verifies that the getViewsData() handler registers the table under the
   * expected key so Views can discover delivery log fields for admin dashboards.
   */
  public function testViewsDataHandlerExposesDeliveryLogTable(): void {
    // Arrange: the views module must be available for EntityViewsData
    if (!$this->container->get('module_handler')->moduleExists('views')) {
      $this->markTestSkipped('Views module is not available in the test environment.');
    }

    // Act
    $viewsData = $this->container
      ->get('entity_type.manager')
      ->getHandler('outbound_delivery_log', 'views_data')
      ->getViewsData();

    // Assert: the outbound_delivery_log table is registered
    $this->assertArrayHasKey('outbound_delivery_log', $viewsData);
    $table = $viewsData['outbound_delivery_log'];

    // Table group and provider metadata are set
    $this->assertSame('Outbound Delivery Log', $table['table']['group']);
    $this->assertSame('entity_webhook_broadcast', $table['table']['provider']);
  }

  /**
   * Tests that status filter uses in_operator handler for filtered admin views.
   */
  public function testViewsDataStatusFieldUsesInOperatorFilter(): void {
    // Arrange
    if (!$this->container->get('module_handler')->moduleExists('views')) {
      $this->markTestSkipped('Views module is not available in the test environment.');
    }

    // Act
    $viewsData = $this->container
      ->get('entity_type.manager')
      ->getHandler('outbound_delivery_log', 'views_data')
      ->getViewsData();

    // Assert
    $this->assertSame(
      'in_operator',
      $viewsData['outbound_delivery_log']['status']['filter']['id'],
    );
  }

  /**
   * Tests that getStatusOptions returns all four valid statuses.
   */
  public function testViewsDataGetStatusOptionsReturnsAllFourStatuses(): void {
    // Arrange + Act
    $options = \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogViewsData::getStatusOptions();

    // Assert
    $this->assertArrayHasKey('pending', $options);
    $this->assertArrayHasKey('success', $options);
    $this->assertArrayHasKey('failed', $options);
    $this->assertArrayHasKey('abandoned', $options);
    $this->assertCount(4, $options);
  }

  /**
   * Tests that getEventOptions returns all three CRUD event types.
   */
  public function testViewsDataGetEventOptionsReturnsAllThreeCrudEvents(): void {
    // Arrange + Act
    $options = \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogViewsData::getEventOptions();

    // Assert
    $this->assertArrayHasKey('insert', $options);
    $this->assertArrayHasKey('update', $options);
    $this->assertArrayHasKey('delete', $options);
    $this->assertCount(3, $options);
  }

  // ---------------------------------------------------------------------------
  // Default field values
  // ---------------------------------------------------------------------------

  /**
   * Tests that default field values are applied on entity creation.
   */
  public function testDefaultFieldValuesAreAppliedOnCreate(): void {
    // Arrange + Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log */
    $log = OutboundDeliveryLog::create([
      'subscription' => 'defaults_sub',
      'entity_type' => 'node',
      'entity_id' => '1',
      'event' => 'insert',
      'payload_hash' => str_repeat('k', 64),
    ]);
    $log->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($log->id());

    // Assert defaults
    $this->assertSame(1, $reloaded->getAttempt());
    $this->assertSame(5, $reloaded->getMaxAttempts());
    $this->assertSame('pending', $reloaded->getStatus());
  }

}
