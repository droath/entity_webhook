<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for WebhookSourceType config entities.
 *
 * A WebhookSourceType defines how to process payloads from a specific external
 * source, including field mappings, identifier fields, and verification config.
 */
interface WebhookSourceTypeInterface extends ConfigEntityInterface {
  /**
   * Returns the field mappings for this source type.
   *
   * @return \Drupal\entity_webhook\Entity\FieldMapping[]
   *   Indexed array of FieldMapping value objects.
   */
  public function getFieldMappings(): array;

  /**
   * Returns only the field mappings designated as identifiers.
   *
   * @return \Drupal\entity_webhook\Entity\FieldMapping[]
   *   Indexed array of FieldMapping value objects with is_identifier = TRUE.
   */
  public function getIdentifierMappings(): array;

  /**
   * Returns the verification plugin ID configured for this source type.
   *
   * @return string
   *   The plugin ID, or an empty string if no verification is configured.
   */
  public function getVerificationPlugin(): string;

  /**
   * Returns the configuration array for the configured verification plugin.
   *
   * @return array<string, mixed>
   *   Plugin configuration keyed by plugin config key.
   */
  public function getVerificationConfig(): array;

  /**
   * Returns the parent endpoint machine name stored on this source type.
   *
   * @return string
   *   The endpoint ID, or an empty string if no endpoint has been stored.
   */
  public function getEndpointId(): string;

  /**
   * Returns the parent WebhookEndpoint entity, if one is stored.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null
   *   The endpoint entity, or NULL if no endpoint is stored or it no longer
   *   exists.
   */
  public function getEndpoint(): ?WebhookEndpointInterface;

  /**
   * Returns the entity operation configured for this source type.
   *
   * @return string
   *   The operation: 'upsert' or 'delete'.
   */
  public function getOperation(): string;

  /**
   * Returns the payload processor plugin ID configured for this source type.
   *
   * @return string
   *   The plugin ID, or an empty string if no processor is configured.
   */
  public function getPayloadProcessor(): string;

  /**
   * Returns the payload processor plugin configuration.
   *
   * @return array<string, mixed>
   *   Plugin configuration keyed by plugin config key.
   */
  public function getPayloadProcessorConfig(): array;
}
