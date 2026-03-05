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

  /** The underlying Drupal queue instance. */
  private readonly QueueInterface $queue;

  /**
   * Constructs a WebhookQueueService.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The Drupal queue factory service.
   */
  public function __construct(QueueFactory $queueFactory) {
    $this->queue = $queueFactory->get(self::QUEUE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function enqueue(WebhookQueueItem $item): bool {
    return (bool) $this->queue->createItem($item->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function getQueueDepth(): int {
    return $this->queue->numberOfItems();
  }

}
