<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for OutboundFieldMapping config entities.
 *
 * An OutboundFieldMapping belongs to a parent OutboundSubscription and maps
 * one entity field to a key in the outbound JSON payload, with an optional
 * FieldValueMutation plugin applied to the value.
 *
 * ID format: {subscription_id}.{output_key}
 */
interface OutboundFieldMappingInterface extends ConfigEntityInterface {

  /**
   * Returns the parent OutboundSubscription machine name.
   *
   * @return string
   *   The subscription ID.
   */
  public function getSubscriptionId(): string;

  /**
   * Returns the parent OutboundSubscription entity.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null
   *   The subscription entity, or NULL if not found.
   */
  public function getSubscription(): ?OutboundSubscriptionInterface;

  /**
   * Returns the source Drupal entity field machine name.
   *
   * @return string
   *   The field name (e.g., 'title', 'field_body').
   */
  public function getEntityField(): string;

  /**
   * Returns the output key name in the webhook JSON payload.
   *
   * @return string
   *   The JSON key name.
   */
  public function getOutputKey(): string;

  /**
   * Returns the FieldValueMutation plugin ID.
   *
   * @return string
   *   The plugin ID, or an empty string when no mutation is applied.
   */
  public function getMutationPlugin(): string;

  /**
   * Returns the configuration for the FieldValueMutation plugin.
   *
   * @return array<string, mixed>
   *   Plugin configuration array.
   */
  public function getMutationConfig(): array;

}
