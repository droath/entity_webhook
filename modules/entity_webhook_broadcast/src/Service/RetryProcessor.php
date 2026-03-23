<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface;
use Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem;
use Drupal\entity_webhook_broadcast\Queue\OutboundQueueServiceInterface;

/**
 * Loads pending delivery logs and re-enqueues them for retry.
 *
 * Drupal's Queue API does not support delayed processing natively, so a
 * cron-driven processor queries the delivery log table for entries whose
 * next_retry_at has passed and re-enqueues them as new queue items with
 * an incremented attempt count.
 */
class RetryProcessor implements RetryProcessorInterface {

  /**
   * Constructs a RetryProcessor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager for querying delivery log entries.
   * @param \Drupal\entity_webhook_broadcast\Queue\OutboundQueueServiceInterface $queueService
   *   The outbound queue service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OutboundQueueServiceInterface $queueService,
    private readonly TimeInterface $time,
    private readonly LoggerChannelInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function processDue(): int {
    $logs = $this->loadDueLogs();
    $count = 0;

    foreach ($logs as $log) {
      $this->reenqueue($log);
      $count++;
    }

    if ($count > 0) {
      $this->logger->info(
        'Outbound retry processor re-enqueued @count delivery log entries.',
        ['@count' => $count],
      );
    }

    return $count;
  }

  /**
   * Loads delivery log entities that are pending and due for retry.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface[]
   *   Delivery log entities ready to be re-enqueued.
   */
  private function loadDueLogs(): array {
    $storage = $this->entityTypeManager->getStorage('outbound_delivery_log');
    $now = (int) $this->time->getRequestTime();

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'pending')
      ->condition('next_retry_at', $now, '<=')
      ->execute();

    if (empty($ids)) {
      return [];
    }

    return array_filter(
      $storage->loadMultiple($ids),
      fn($log) => $log instanceof OutboundDeliveryLogInterface,
    );
  }

  /**
   * Re-enqueues a single delivery log entry with an incremented attempt.
   *
   * The delivery log's attempt counter is incremented and next_retry_at is
   * cleared before the new queue item is created, so the log reflects the
   * new in-flight state immediately.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface $log
   *   The delivery log entry to retry.
   */
  private function reenqueue(OutboundDeliveryLogInterface $log): void {
    $nextAttempt = $log->getAttempt() + 1;
    $log->incrementAttempt();
    $log->setNextRetryAt(NULL);
    $log->save();

    $item = new OutboundQueueItem(
      endpointId: '',
      subscriptionId: $log->getSubscriptionId(),
      entityTypeId: $log->getSourceEntityType(),
      entityId: $log->getSourceEntityId(),
      event: $log->getEvent(),
      payload: $log->getPayload(),
      deliveryLogId: (string) $log->id(),
      attempt: $nextAttempt,
      dispatchedAt: new \DateTimeImmutable(),
    );

    $this->queueService->enqueue($item);
  }

}
