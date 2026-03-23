<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for dispatching entity events to outbound subscriptions.
 *
 * The dispatcher finds matching OutboundEndpoint configs, iterates their active
 * subscriptions, builds payloads, and enqueues OutboundQueueItem instances for
 * async delivery.
 */
interface OutboundDispatcherInterface {

  /**
   * Dispatches a CRUD event for the given entity to all matching subscriptions.
   *
   * Finds OutboundEndpoint configs matching the entity type, bundle, and event,
   * then for each active subscription builds the payload and enqueues a queue
   * item for async delivery.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that triggered the event. For delete events this is the
   *   pre-deletion entity state captured before the database record is removed.
   * @param string $event
   *   The CRUD event name: 'insert', 'update', or 'delete'.
   */
  public function dispatch(EntityInterface $entity, string $event): void;

}
