<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Queue;

/**
 * Defines the interface for the webhook queue service.
 *
 * Provides a typed facade over Drupal's Queue API for enqueuing and
 * claiming webhook payload items for asynchronous processing.
 */
interface WebhookQueueServiceInterface {
  /**
   * Enqueues a webhook payload item for asynchronous processing.
   *
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item to enqueue.
   *
   * @return bool
   *   TRUE if the item was successfully added to the queue, FALSE otherwise.
   */
  public function enqueue(WebhookQueueItem $item): bool;

  /**
   * Returns the number of items currently in the queue.
   *
   * @return int
   *   The queue depth.
   */
  public function getQueueDepth(): int;
}
