<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for entity upsert operations.
 *
 * Orchestrates entity lookup by identifier fields and either creates a new
 * entity or updates the existing one, applying all field mappings to the
 * extracted payload values.
 */
interface EntityUpsertServiceInterface {
  /**
   * Creates or updates an entity based on field mappings and payload data.
   *
   * Mappings flagged as identifiers are used to look up an existing entity.
   * If a match is found, it is updated; otherwise a new entity is created.
   * Non-identifier mappings are always applied as field values.
   *
   * @param string $entityTypeId
   *   The Drupal entity type machine name (e.g., 'node').
   * @param string $bundle
   *   The bundle machine name (e.g., 'article').
   * @param \Drupal\entity_webhook\Entity\FieldMapping[] $mappings
   *   All field mappings to apply, including identifier fields.
   * @param array<string, mixed> $extractedValues
   *   Payload values keyed by entity field machine name (post JSONPath
   *   extraction). Identifier fields must be present as keys.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The saved entity (new or updated).
   */
  public function upsert(
    string $entityTypeId,
    string $bundle,
    array $mappings,
    array $extractedValues,
  ): EntityInterface;

  /**
   * Finds or creates an entity without saving it or applying field values.
   *
   * This method separates the lookup/creation step from saving, so callers
   * can inspect the entity, dispatch events, and control when the save occurs.
   *
   * @param string $entityTypeId
   *   The Drupal entity type machine name (e.g., 'node').
   * @param string $bundle
   *   The bundle machine name (e.g., 'article').
   * @param \Drupal\entity_webhook\Entity\FieldMapping[] $mappings
   *   All field mappings, used to identify lookup criteria.
   * @param array<string, mixed> $extractedValues
   *   Payload values keyed by entity field machine name.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An unsaved entity instance (new or existing, not yet field-populated).
   */
  public function resolveEntity(
    string $entityTypeId,
    string $bundle,
    array $mappings,
    array $extractedValues,
  ): EntityInterface;

  /**
   * Applies field mappings to an entity from extracted payload values.
   *
   * Identifier fields and structural keys (entity ID, bundle) are skipped.
   * Only fieldable entities support set(); non-fieldable entities are a no-op.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to populate.
   * @param \Drupal\entity_webhook\Entity\FieldMapping[] $mappings
   *   All field mappings to apply.
   * @param array<string, mixed> $extractedValues
   *   Payload values keyed by entity field machine name.
   */
  public function applyFieldValues(
    EntityInterface $entity,
    array $mappings,
    array $extractedValues,
  ): void;
}
