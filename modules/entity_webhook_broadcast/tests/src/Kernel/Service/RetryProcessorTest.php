<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Service;

use Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLog;
use Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem;
use Drupal\entity_webhook_broadcast\Service\RetryProcessorInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for RetryProcessor: cron-based delivery log re-enqueuing.
 *
 * Verifies that the retry processor correctly:
 * - Queries pending delivery logs whose next_retry_at has passed
 * - Re-enqueues each entry as a new OutboundQueueItem with an incremented attempt
 * - Clears next_retry_at on the log entry after re-enqueuing
 * - Skips logs that are not yet due (future next_retry_at)
 * - Skips logs with statuses other than 'pending'
 * - Returns the correct re-enqueue count
 *
 * @group entity_webhook_broadcast
 */
class RetryProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'entity_webhook_broadcast',
    'user',
    'system',
  ];

  /**
   * The retry processor under test.
   */
  private RetryProcessorInterface $retryProcessor;

  /**
   * The raw Drupal queue for inspecting enqueued items.
   */
  private \Drupal\Core\Queue\QueueInterface $rawQueue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('outbound_delivery_log');
    $this->installSchema('system', ['sequences']);

    $this->retryProcessor = $this->container->get('entity_webhook_broadcast.retry_processor');
    $this->rawQueue = $this->container->get('queue')->get('entity_webhook_broadcast');
  }

  // ---------------------------------------------------------------------------
  // processDue() — items that ARE due
  // ---------------------------------------------------------------------------

  /**
   * Tests that a pending log with a past next_retry_at is re-enqueued.
   */
  public function testProcessDueReenqueuesPendingLogWithPastRetryTime(): void {
    // Arrange: create a pending log entry whose retry time is in the past
    $pastTimestamp = time() - 60;
    $log = OutboundDeliveryLog::create([
      'subscription' => 'retry_sub',
      'entity_type' => 'node',
      'entity_id' => '100',
      'event' => 'insert',
      'payload_hash' => str_repeat('a', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => $pastTimestamp,
      'payload' => json_encode(['title' => 'Test'], JSON_THROW_ON_ERROR),
    ]);
    $log->save();
    $logId = (string) $log->id();

    $queueDepthBefore = $this->rawQueue->numberOfItems();

    // Act
    $count = $this->retryProcessor->processDue();

    // Assert: one item was re-enqueued
    $this->assertSame(1, $count);
    $this->assertSame($queueDepthBefore + 1, $this->rawQueue->numberOfItems());

    // Verify the enqueued item has the correct delivery log ID
    $queueItem = $this->rawQueue->claimItem();
    $this->assertNotFalse($queueItem);
    $item = OutboundQueueItem::fromArray((array) $queueItem->data);
    $this->assertSame($logId, $item->deliveryLogId);
  }

  /**
   * Tests that the re-enqueued item carries an incremented attempt counter.
   */
  public function testProcessDueReenqueuesItemWithIncrementedAttemptNumber(): void {
    // Arrange: log is on attempt 2 — re-enqueued item should use attempt 3
    $originalAttempt = 2;
    $log = OutboundDeliveryLog::create([
      'subscription' => 'attempt_sub',
      'entity_type' => 'node',
      'entity_id' => '101',
      'event' => 'update',
      'payload_hash' => str_repeat('b', 64),
      'attempt' => $originalAttempt,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => time() - 30,
    ]);
    $log->save();

    // Act
    $this->retryProcessor->processDue();

    // Assert: queue item carries attempt = originalAttempt + 1
    $queueItem = $this->rawQueue->claimItem();
    $this->assertNotFalse($queueItem);
    $item = OutboundQueueItem::fromArray((array) $queueItem->data);
    $this->assertSame($originalAttempt + 1, $item->attempt);
  }

  /**
   * Tests that next_retry_at is cleared on the log after re-enqueuing.
   */
  public function testProcessDueClearsNextRetryAtAfterReenqueue(): void {
    // Arrange
    $log = OutboundDeliveryLog::create([
      'subscription' => 'clear_ts_sub',
      'entity_type' => 'node',
      'entity_id' => '102',
      'event' => 'delete',
      'payload_hash' => str_repeat('c', 64),
      'attempt' => 1,
      'max_attempts' => 3,
      'status' => 'pending',
      'next_retry_at' => time() - 10,
    ]);
    $log->save();
    $logId = $log->id();

    // Act
    $this->retryProcessor->processDue();

    // Assert: next_retry_at is NULL after processing
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($logId);
    $this->assertNull($reloaded->getNextRetryAt());
  }

  /**
   * Tests that the log attempt counter is incremented after re-enqueuing.
   */
  public function testProcessDueIncrementsLogAttemptCounterAfterReenqueue(): void {
    // Arrange
    $initialAttempt = 1;
    $log = OutboundDeliveryLog::create([
      'subscription' => 'inc_attempt_sub',
      'entity_type' => 'node',
      'entity_id' => '103',
      'event' => 'insert',
      'payload_hash' => str_repeat('d', 64),
      'attempt' => $initialAttempt,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => time() - 5,
    ]);
    $log->save();
    $logId = $log->id();

    // Act
    $this->retryProcessor->processDue();

    // Assert: the log now reflects the incremented attempt
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('outbound_delivery_log');
    $storage->resetCache();
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $reloaded */
    $reloaded = $storage->load($logId);
    $this->assertSame($initialAttempt + 1, $reloaded->getAttempt());
  }

  /**
   * Tests that the re-enqueued item carries subscription, entity, and event metadata.
   */
  public function testProcessDueReenqueuesItemWithCorrectMetadata(): void {
    // Arrange
    $log = OutboundDeliveryLog::create([
      'subscription' => 'metadata_sub',
      'entity_type' => 'commerce_product',
      'entity_id' => '777',
      'event' => 'update',
      'payload_hash' => str_repeat('e', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => time() - 120,
    ]);
    $log->save();

    // Act
    $this->retryProcessor->processDue();

    // Assert: queue item metadata matches the log entry fields
    $queueItem = $this->rawQueue->claimItem();
    $this->assertNotFalse($queueItem);
    $item = OutboundQueueItem::fromArray((array) $queueItem->data);

    $this->assertSame('metadata_sub', $item->subscriptionId);
    $this->assertSame('commerce_product', $item->entityTypeId);
    $this->assertSame('777', $item->entityId);
    $this->assertSame('update', $item->event);
  }

  /**
   * Tests that processDue re-enqueues all due items when multiple exist.
   */
  public function testProcessDueReenqueuesAllDueItemsWhenMultipleExist(): void {
    // Arrange: three logs all past due
    for ($i = 1; $i <= 3; $i++) {
      OutboundDeliveryLog::create([
        'subscription' => "multi_sub_{$i}",
        'entity_type' => 'node',
        'entity_id' => (string) $i,
        'event' => 'insert',
        'payload_hash' => str_repeat((string) $i, 64),
        'attempt' => 1,
        'max_attempts' => 5,
        'status' => 'pending',
        'next_retry_at' => time() - 60,
      ])->save();
    }

    $queueDepthBefore = $this->rawQueue->numberOfItems();

    // Act
    $count = $this->retryProcessor->processDue();

    // Assert
    $this->assertSame(3, $count);
    $this->assertSame($queueDepthBefore + 3, $this->rawQueue->numberOfItems());
  }

  // ---------------------------------------------------------------------------
  // processDue() — items that should NOT be processed
  // ---------------------------------------------------------------------------

  /**
   * Tests that a pending log with a future next_retry_at is not re-enqueued.
   */
  public function testProcessDueSkipsPendingLogWithFutureRetryTime(): void {
    // Arrange: next_retry_at is one hour in the future
    OutboundDeliveryLog::create([
      'subscription' => 'future_sub',
      'entity_type' => 'node',
      'entity_id' => '200',
      'event' => 'insert',
      'payload_hash' => str_repeat('f', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => time() + 3600,
    ])->save();

    $queueDepthBefore = $this->rawQueue->numberOfItems();

    // Act
    $count = $this->retryProcessor->processDue();

    // Assert: nothing was re-enqueued
    $this->assertSame(0, $count);
    $this->assertSame($queueDepthBefore, $this->rawQueue->numberOfItems());
  }

  /**
   * Tests that a log entry with status 'success' is not re-enqueued.
   */
  public function testProcessDueSkipsSuccessStatusLogs(): void {
    // Arrange: log was already successful — past retry time but wrong status
    OutboundDeliveryLog::create([
      'subscription' => 'success_skip_sub',
      'entity_type' => 'node',
      'entity_id' => '201',
      'event' => 'insert',
      'payload_hash' => str_repeat('g', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'success',
      'next_retry_at' => time() - 60,
    ])->save();

    $queueDepthBefore = $this->rawQueue->numberOfItems();

    // Act
    $count = $this->retryProcessor->processDue();

    // Assert
    $this->assertSame(0, $count);
    $this->assertSame($queueDepthBefore, $this->rawQueue->numberOfItems());
  }

  /**
   * Tests that a log entry with status 'abandoned' is not re-enqueued.
   */
  public function testProcessDueSkipsAbandonedStatusLogs(): void {
    // Arrange
    OutboundDeliveryLog::create([
      'subscription' => 'abandoned_skip_sub',
      'entity_type' => 'node',
      'entity_id' => '202',
      'event' => 'update',
      'payload_hash' => str_repeat('h', 64),
      'attempt' => 5,
      'max_attempts' => 5,
      'status' => 'abandoned',
      'next_retry_at' => time() - 60,
    ])->save();

    $queueDepthBefore = $this->rawQueue->numberOfItems();

    // Act
    $count = $this->retryProcessor->processDue();

    // Assert
    $this->assertSame(0, $count);
    $this->assertSame($queueDepthBefore, $this->rawQueue->numberOfItems());
  }

  /**
   * Tests that a pending log with no next_retry_at set is not re-enqueued.
   *
   * A log with next_retry_at = NULL has not been scheduled for retry and
   * should not be picked up by the retry processor.
   */
  public function testProcessDueSkipsPendingLogWithNoRetryTimestamp(): void {
    // Arrange
    OutboundDeliveryLog::create([
      'subscription' => 'no_ts_sub',
      'entity_type' => 'node',
      'entity_id' => '203',
      'event' => 'insert',
      'payload_hash' => str_repeat('i', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => NULL,
    ])->save();

    $queueDepthBefore = $this->rawQueue->numberOfItems();

    // Act
    $count = $this->retryProcessor->processDue();

    // Assert
    $this->assertSame(0, $count);
    $this->assertSame($queueDepthBefore, $this->rawQueue->numberOfItems());
  }

  /**
   * Tests that processDue returns zero when no logs are due.
   */
  public function testProcessDueReturnsZeroWhenNoLogsAreDue(): void {
    // Arrange: no delivery log entities exist

    // Act
    $count = $this->retryProcessor->processDue();

    // Assert
    $this->assertSame(0, $count);
  }

  // ---------------------------------------------------------------------------
  // Mixed due and not-due logs
  // ---------------------------------------------------------------------------

  /**
   * Tests that only due logs are processed when both due and future logs exist.
   */
  public function testProcessDueOnlyProcessesDueLogsWhenMixedTimestampsExist(): void {
    // Arrange: one past-due log and one future log
    OutboundDeliveryLog::create([
      'subscription' => 'due_sub',
      'entity_type' => 'node',
      'entity_id' => '300',
      'event' => 'insert',
      'payload_hash' => str_repeat('j', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => time() - 60,
    ])->save();

    OutboundDeliveryLog::create([
      'subscription' => 'not_due_sub',
      'entity_type' => 'node',
      'entity_id' => '301',
      'event' => 'update',
      'payload_hash' => str_repeat('k', 64),
      'attempt' => 1,
      'max_attempts' => 5,
      'status' => 'pending',
      'next_retry_at' => time() + 3600,
    ])->save();

    $queueDepthBefore = $this->rawQueue->numberOfItems();

    // Act
    $count = $this->retryProcessor->processDue();

    // Assert: only the past-due log was re-enqueued
    $this->assertSame(1, $count);
    $this->assertSame($queueDepthBefore + 1, $this->rawQueue->numberOfItems());
  }

}
