<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for entity lookup by identifier field values.
 *
 * Supports single and composite key lookups. Returns NULL when no entity
 * matches all provided criteria simultaneously.
 */
interface EntityLookupServiceInterface {
  /**
   * Finds an entity matching all provided field criteria.
   *
   * When multiple criteria are provided, all must match (AND logic). Returns
   * the first match when multiple results exist, or NULL when none exist.
   * Returns NULL immediately when the criteria array is empty.
   *
   * @param string $entityTypeId
   *   The Drupal entity type machine name (e.g., 'node', 'user').
   * @param array<string, string> $criteria
   *   Associative array of field machine name => value pairs for lookup.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The matched entity, or NULL if not found.
   */
  public function findEntity(string $entityTypeId, array $criteria): ?EntityInterface;
}
