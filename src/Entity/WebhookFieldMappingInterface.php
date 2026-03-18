<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for WebhookFieldMapping config entities.
 *
 * A WebhookFieldMapping is a standalone config entity that maps a single
 * Drupal entity field to a value extracted from a webhook payload, using a
 * configured ValueResolver plugin and an optional FieldValueMutation plugin.
 * Each mapping belongs to exactly one WebhookSourceType (one-to-many).
 */
interface WebhookFieldMappingInterface extends ConfigEntityInterface {
  /**
   * Returns the parent WebhookSourceType machine name.
   *
   * @return string
   *   The source type ID.
   */
  public function getSourceTypeId(): string;

  /**
   * Returns the parent WebhookSourceType entity.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface|null
   *   The source type entity, or NULL if it no longer exists.
   */
  public function getSourceType(): ?WebhookSourceTypeInterface;

  /**
   * Returns the target Drupal entity field machine name.
   *
   * @return string
   *   The entity field machine name (e.g. 'title', 'field_external_id').
   */
  public function getEntityField(): string;

  /**
   * Returns whether this mapping is used as an identifier for entity lookup.
   *
   * @return bool
   *   TRUE if this field is used as an upsert identifier.
   */
  public function isIdentifier(): bool;

  /**
   * Returns the ValueResolver plugin ID configured for this mapping.
   *
   * @return string
   *   The plugin ID (e.g. 'json_path', 'static_value').
   */
  public function getResolver(): string;

  /**
   * Returns the configuration array for the configured ValueResolver plugin.
   *
   * @return array<string, mixed>
   *   Plugin-specific configuration keyed by config key.
   */
  public function getResolverConfig(): array;

  /**
   * Returns the FieldValueMutation plugin ID configured for this mapping.
   *
   * @return string
   *   The plugin ID, or an empty string when no mutation is applied.
   */
  public function getMutationPlugin(): string;

  /**
   * Returns the configuration array for the configured mutation plugin.
   *
   * @return array<string, mixed>
   *   Plugin-specific configuration keyed by config key.
   */
  public function getMutationConfig(): array;

  /**
   * Returns a FieldMapping value object constructed from this entity's data.
   *
   * Serves as the adapter between config entity storage and the processing
   * pipeline, which consumes FieldMapping value objects exclusively.
   *
   * @return \Drupal\entity_webhook\Entity\FieldMapping
   *   A lean value object representing this mapping.
   */
  public function toFieldMapping(): FieldMapping;
}
