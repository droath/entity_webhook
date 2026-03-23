<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Queue;

/**
 * Defines the interface for the outbound webhook queue service.
 *
 * Provides a typed facade over Drupal's Queue API for enqueuing outbound
 * webhook delivery items for asynchronous processing.
 */
interface OutboundQueueServiceInterface {

  /**
   * Enqueues an outbound webhook item for asynchronous delivery.
   *
   * @param \Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem $item
   *   The queue item to enqueue.
   */
  public function enqueue(OutboundQueueItem $item): void;

  /**
   * Returns the number of items currently in the outbound delivery queue.
   *
   * @return int
   *   The queue depth.
   */
  public function getQueueDepth(): int;

}
