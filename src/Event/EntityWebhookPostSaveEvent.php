<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after an entity is successfully saved during webhook processing.
 *
 * This event is informational. Subscribers can react to the completed upsert
 * but cannot prevent or reverse the save operation.
 */
class EntityWebhookPostSaveEvent extends Event {
  /**
   * Constructs an EntityWebhookPostSaveEvent.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was just saved.
   * @param array<string, mixed> $payload
   *   The decoded JSON payload that triggered this processing.
   * @param string $endpointId
   *   The WebhookEndpoint config entity ID.
   * @param string $sourceType
   *   The WebhookSourceType config entity ID.
   * @param bool $wasCreated
   *   TRUE if the entity was newly created, FALSE if it was updated.
   */
  public function __construct(
    public readonly EntityInterface $entity,
    public readonly array $payload,
    public readonly string $endpointId,
    public readonly string $sourceType,
    public readonly bool $wasCreated,
  ) {
  }

}
