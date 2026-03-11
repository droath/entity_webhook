<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

/**
 * Value object representing a single field mapping within a WebhookSourceType.
 *
 * A field mapping connects a JSONPath expression in an incoming webhook payload
 * to a specific Drupal entity field. The is_identifier flag designates the
 * field as a lookup key for entity upsert operations.
 */
final readonly class FieldMapping {
  /**
   * Constructs a FieldMapping value object.
   *
   * @param string $entityField
   *   The Drupal entity field machine name.
   * @param string $jsonPath
   *   The JSONPath expression to extract the value from the payload.
   * @param bool $isIdentifier
   *   Whether this field is used as an identifier for entity lookup.
   */
  public function __construct(
    public string $entityField,
    public string $jsonPath,
    public bool $isIdentifier = FALSE,
  ) {
  }

  /**
   * Creates a FieldMapping from a configuration array.
   *
   * @param array<string, mixed> $data
   *   Array with keys: entity_field, json_path, is_identifier.
   *
   * @return self
   *   A new FieldMapping instance.
   */
  public static function fromArray(array $data): self {
    return new self(
      entityField: (string) ($data['entity_field'] ?? ''),
      jsonPath: (string) ($data['json_path'] ?? ''),
      isIdentifier: (bool) ($data['is_identifier'] ?? FALSE),
    );
  }

  /**
   * Returns the field mapping as a plain array for config storage.
   *
   * @return array<string, mixed>
   *   Array with keys: entity_field, json_path, is_identifier.
   */
  public function toArray(): array {
    return [
      'entity_field' => $this->entityField,
      'json_path' => $this->jsonPath,
      'is_identifier' => $this->isIdentifier,
    ];
  }
}
