<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for WebhookEndpoint config entities.
 *
 * A WebhookEndpoint represents a single webhook URL endpoint. It defines the
 * target Drupal entity type and references one or more WebhookSourceType IDs.
 */
interface WebhookEndpointInterface extends ConfigEntityInterface {
  /**
   * Returns the target entity type ID for this endpoint.
   *
   * @return string
   *   The Drupal entity type machine name (e.g., 'node', 'user').
   */
  public function getTargetEntityTypeId(): string;

  /**
   * Returns the list of WebhookSourceType IDs associated with this endpoint.
   *
   * @return string[]
   *   Array of WebhookSourceType machine names.
   */
  public function getSourceTypeIds(): array;

  /**
   * Returns whether the given source type ID is associated with this endpoint.
   *
   * @param string $sourceTypeId
   *   The WebhookSourceType machine name.
   *
   * @return bool
   *   TRUE if the source type is associated with this endpoint.
   */
  public function hasSourceType(string $sourceTypeId): bool;
}
