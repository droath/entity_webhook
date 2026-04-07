<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Drupal\Core\Entity\EntityInterface;

/**
 * Value object representing the outcome of processing a webhook queue item.
 */
final readonly class WebhookProcessResult {
  /**
   * Constructs a WebhookProcessResult.
   *
   * @param bool $success
   *   Whether the processing succeeded.
   * @param string|null $operation
   *   The operation performed: 'created', 'updated', 'deleted', 'skipped', or
   *   NULL on error.
   * @param int|string|null $entityId
   *   The entity ID, or NULL when no entity was involved.
   * @param string|null $entityTypeId
   *   The entity type ID, or NULL when no entity was involved.
   * @param string|null $bundle
   *   The entity bundle, or NULL when no entity was involved.
   * @param string|null $label
   *   The entity label, or NULL when no entity was involved.
   * @param string|null $error
   *   The error message, or NULL on success.
   * @param array<string, mixed> $errorDetails
   *   Additional error context, empty on success.
   */
  public function __construct(
    public bool $success,
    public ?string $operation,
    public int|string|NULL $entityId,
    public ?string $entityTypeId,
    public ?string $bundle,
    public ?string $label,
    public ?string $error,
    public array $errorDetails = [],
  ) {
  }

  /**
   * Creates a successful result from an entity.
   *
   * @param string $operation
   *   The operation performed: 'created', 'updated', or 'deleted'.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was processed.
   *
   * @return self
   *   A success result.
   */
  public static function success(string $operation, EntityInterface $entity): self {
    return new self(
      success: TRUE,
      operation: $operation,
      entityId: $entity->id(),
      entityTypeId: $entity->getEntityTypeId(),
      bundle: $entity->bundle(),
      label: $entity->label(),
      error: NULL,
    );
  }

  /**
   * Creates an error result.
   *
   * @param string $message
   *   The human-readable error description.
   * @param array<string, mixed> $details
   *   Optional additional error context.
   *
   * @return self
   *   An error result.
   */
  public static function error(string $message, array $details = []): self {
    return new self(
      success: FALSE,
      operation: NULL,
      entityId: NULL,
      entityTypeId: NULL,
      bundle: NULL,
      label: NULL,
      error: $message,
      errorDetails: $details,
    );
  }

  /**
   * Returns an array representation suitable for use in a JSON response.
   *
   * @return array<string, mixed>
   *   The response array.
   */
  public function toResponseArray(): array {
    if ($this->success) {
      return [
        'status' => 'success',
        'operation' => $this->operation,
        'entity_id' => $this->entityId,
        'entity_type' => $this->entityTypeId,
        'bundle' => $this->bundle,
        'label' => $this->label,
      ];
    }

    return [
      'status' => 'error',
      'error' => $this->error,
      'details' => $this->errorDetails,
    ];
  }
}
