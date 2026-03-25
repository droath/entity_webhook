<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for OutboundEndpoint config entities.
 *
 * An OutboundEndpoint watches a specific entity type, optional bundle, and set
 * of CRUD events, and routes dispatches to its child OutboundSubscription
 * entities.
 */
interface OutboundEndpointInterface extends ConfigEntityInterface {

  /**
   * Returns the entity type machine name to watch.
   *
   * @return string
   *   The Drupal entity type ID (e.g., 'node', 'user').
   */
  public function getWatchedEntityType(): string;

  /**
   * Returns the entity bundle filter, or NULL to match all bundles.
   *
   * @return string|null
   *   The bundle machine name, or NULL for all bundles.
   */
  public function getEntityBundle(): ?string;

  /**
   * Returns the CRUD event names this endpoint is watching.
   *
   * @return string[]
   *   Array of event names: 'insert', 'update', 'delete'.
   */
  public function getEvents(): array;

  /**
   * Returns whether this endpoint is enabled.
   *
   * @return bool
   *   TRUE if the endpoint is active.
   */
  public function isEnabled(): bool;

  /**
   * Returns the condition plugin collection for this endpoint.
   *
   * @return \Drupal\Core\Condition\ConditionPluginCollection
   *   The condition plugin collection.
   */
  public function getConditions(): ConditionPluginCollection;

  /**
   * Returns only the conditions that have been actively configured.
   *
   * Filters the raw condition configuration to exclude conditions whose
   * config consists only of default meta-keys (id, negate, context_mapping)
   * with no meaningful custom values.
   *
   * @return array<string, array<string, mixed>>
   *   Active condition configurations keyed by plugin ID.
   */
  public function getActiveConditions(): array;

}
