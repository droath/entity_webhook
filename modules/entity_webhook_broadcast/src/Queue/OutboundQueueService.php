<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Queue;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

/**
 * Manages enqueueing outbound webhook delivery items using Drupal's Queue API.
 *
 * Acts as a typed facade so callers work with OutboundQueueItem objects
 * rather than raw queue data arrays.
 */
class OutboundQueueService implements OutboundQueueServiceInterface {

  /** The name of the Drupal queue used for outbound webhook delivery. */
  private const string QUEUE_NAME = 'entity_webhook_broadcast';

  /** The underlying Drupal queue instance, lazily initialized. */
  private ?QueueInterface $queue = NULL;

  /**
   * Constructs an OutboundQueueService.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The Drupal queue factory service.
   */
  public function __construct(
    private readonly QueueFactory $queueFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function enqueue(OutboundQueueItem $item): void {
    $this->getQueue()->createItem($item->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function getQueueDepth(): int {
    return $this->getQueue()->numberOfItems();
  }

  /**
   * Returns the queue instance, initializing it on first access.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The underlying Drupal queue.
   */
  private function getQueue(): QueueInterface {
    return $this->queue ??= $this->queueFactory->get(self::QUEUE_NAME);
  }

}
