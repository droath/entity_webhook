<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\entity_webhook_broadcast\Service\OutboundDispatcherInterface;

/**
 * Hook implementations that feed entity CRUD events into the dispatch pipeline.
 *
 * Insert and update events are dispatched immediately with the current entity
 * state. Delete events use hook_entity_predelete so the entity state is still
 * in memory before the database record is removed; this is essential for
 * correct payload capture.
 */
class EntityLifecycleHooks {

  /**
   * Constructs EntityLifecycleHooks.
   *
   * @param \Drupal\entity_webhook_broadcast\Service\OutboundDispatcherInterface $dispatcher
   *   The outbound dispatcher service.
   */
  public function __construct(
    private readonly OutboundDispatcherInterface $dispatcher,
  ) {
  }

  /**
   * Dispatches an 'insert' event when an entity is created.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The newly created entity.
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->dispatcher->dispatch($entity, 'insert');
  }

  /**
   * Dispatches an 'update' event when an entity is saved with changes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The updated entity.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->dispatcher->dispatch($entity, 'update');
  }

  /**
   * Dispatches a 'delete' event before the entity record is removed.
   *
   * Using hook_entity_predelete ensures the entity's field values are still
   * available in memory at payload-build time. This is the correct hook for
   * capturing pre-deletion state for outbound webhooks.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity about to be deleted.
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {
    $this->dispatcher->dispatch($entity, 'delete');
  }

}
