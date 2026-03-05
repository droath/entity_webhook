<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before an entity is saved during webhook processing.
 *
 * Subscribers may call abort() to prevent the entity save from occurring,
 * or modify the entity directly before it is persisted.
 */
class EntityWebhookPreSaveEvent extends Event {
  /** Whether processing should be aborted before saving. */
  private bool $aborted = FALSE;

  /**
   * Constructs an EntityWebhookPreSaveEvent.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity about to be saved.
   * @param array<string, mixed> $payload
   *   The decoded JSON payload that triggered this processing.
   * @param string $endpointId
   *   The WebhookEndpoint config entity ID.
   * @param string $sourceType
   *   The WebhookSourceType config entity ID.
   * @param bool $isNew
   *   TRUE if the entity is new (will be created), FALSE if updating.
   */
  public function __construct(
    public readonly EntityInterface $entity,
    public readonly array $payload,
    public readonly string $endpointId,
    public readonly string $sourceType,
    public readonly bool $isNew,
  ) {
  }

  /**
   * Aborts the entity save operation.
   *
   * When called, the entity will not be saved and no PostSave event will fire.
   */
  public function abort(): void {
    $this->aborted = TRUE;
  }

  /**
   * Returns whether the save operation has been aborted.
   *
   * @return bool
   *   TRUE if a subscriber has called abort(), FALSE otherwise.
   */
  public function isAborted(): bool {
    return $this->aborted;
  }
}
