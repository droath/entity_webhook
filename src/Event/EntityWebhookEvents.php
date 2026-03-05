<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Event;

/**
 * Defines event name constants for the Entity Webhook module.
 *
 * Subscribe to these events to react to or modify entity upsert operations
 * driven by incoming webhook payloads.
 */
final class EntityWebhookEvents {
  /**
   * Fired before an entity is saved during webhook processing.
   *
   * Subscribers may modify the entity, alter the payload, or set an abort
   * flag to prevent the save from occurring.
   *
   * @Event
   *
   * @see \Drupal\entity_webhook\Event\EntityWebhookPreSaveEvent
   */
  public const PRE_SAVE = 'entity_webhook.pre_save';

  /**
   * Fired after an entity is successfully saved during webhook processing.
   *
   * This event is informational only; the entity has already been persisted.
   *
   * @Event
   *
   * @see \Drupal\entity_webhook\Event\EntityWebhookPostSaveEvent
   */
  public const POST_SAVE = 'entity_webhook.post_save';
}
