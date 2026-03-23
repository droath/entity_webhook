<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

/**
 * Re-enqueues pending delivery log entries whose retry time has arrived.
 *
 * Called from hook_cron with a lock so only one process runs at a time.
 */
interface RetryProcessorInterface {

  /**
   * Processes all pending delivery logs that are due for retry.
   *
   * Queries outbound_delivery_log entities where status='pending' AND
   * next_retry_at <= now, then re-enqueues each one as a new OutboundQueueItem
   * with an incremented attempt counter.
   *
   * @return int
   *   The number of items re-enqueued.
   */
  public function processDue(): int;

}
