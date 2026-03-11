<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Queue;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

/**
 * Manages enqueueing webhook payload items using Drupal's Queue API.
 *
 * Acts as a typed facade so callers work with WebhookQueueItem objects
 * rather than raw queue data arrays.
 */
class WebhookQueueService implements WebhookQueueServiceInterface {
  /** The name of the Drupal queue used for webhook processing. */
  private const string QUEUE_NAME = 'entity_webhook_processor';

  /** The underlying Drupal queue instance, lazily initialized. */
  private ?QueueInterface $queue = NULL;

  /**
   * Constructs a WebhookQueueService.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The Drupal queue factory service.
   */
  public function __construct(private readonly QueueFactory $queueFactory) {
  }

  /**
   * {@inheritdoc}
   */
  public function enqueue(WebhookQueueItem $item): bool {
    return (bool) $this->getQueue()->createItem($item->toArray());
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
