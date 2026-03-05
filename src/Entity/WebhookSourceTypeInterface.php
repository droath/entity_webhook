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
}
