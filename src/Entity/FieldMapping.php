<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

/**
 * Value object representing a single field mapping within a WebhookSourceType.
 *
 * A field mapping connects a value resolver plugin to a specific Drupal entity
 * field. The resolver determines how the value is extracted from the payload.
 * An optional mutation plugin transforms the extracted value before storage.
 * The is_identifier flag designates the field as a lookup key for entity
 * upsert operations.
 */
final readonly class FieldMapping {
  /**
   * Constructs a FieldMapping value object.
   *
   * @param string $entityField
   *   The Drupal entity field machine name.
   * @param bool $isIdentifier
   *   Whether this field is used as an identifier for entity lookup.
   * @param string $mutationPlugin
   *   The mutation plugin ID, or an empty string when no mutation is applied.
   * @param array<string, mixed> $mutationConfig
   *   Plugin-specific configuration for the mutation plugin.
   * @param string $resolver
   *   The value resolver plugin ID. Defaults to 'json_path'.
   * @param array<string, mixed> $resolverConfig
   *   Plugin-specific configuration for the value resolver plugin.
   */
  public function __construct(
    public string $entityField,
    public bool $isIdentifier = FALSE,
    public string $mutationPlugin = '',
    public array $mutationConfig = [],
    public string $resolver = 'json_path',
    public array $resolverConfig = [],
  ) {
  }

  /**
   * Creates a FieldMapping from a configuration array.
   *
   * @param array<string, mixed> $data
   *   Array with keys: entity_field, is_identifier, mutation_plugin,
   *   mutation_config, resolver, resolver_config.
   *
   * @return self
   *   A new FieldMapping instance.
   */
  public static function fromArray(array $data): self {
    return new self(
      entityField: (string) ($data['entity_field'] ?? ''),
      isIdentifier: (bool) ($data['is_identifier'] ?? FALSE),
      mutationPlugin: (string) ($data['mutation_plugin'] ?? ''),
      mutationConfig: (array) ($data['mutation_config'] ?? []),
      resolver: (string) ($data['resolver'] ?? 'json_path'),
      resolverConfig: (array) ($data['resolver_config'] ?? []),
    );
  }

  /**
   * Returns the field mapping as a plain array for config storage.
   *
   * @return array<string, mixed>
   *   Array with keys: entity_field, is_identifier, mutation_plugin,
   *   mutation_config, resolver, resolver_config.
   */
  public function toArray(): array {
    return [
      'entity_field' => $this->entityField,
      'is_identifier' => $this->isIdentifier,
      'mutation_plugin' => $this->mutationPlugin,
      'mutation_config' => $this->mutationConfig,
      'resolver' => $this->resolver,
      'resolver_config' => $this->resolverConfig,
    ];
  }
}
