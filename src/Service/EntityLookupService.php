<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Finds existing entities by one or more identifier field values.
 *
 * Supports composite key lookups by accepting an associative array of
 * field => value pairs. All criteria must match (AND logic).
 */
class EntityLookupService implements EntityLookupServiceInterface {
  /**
   * Constructs an EntityLookupService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function findEntity(string $entityTypeId, array $criteria): ?EntityInterface {
    if (empty($criteria)) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $query = $storage->getQuery()->accessCheck(FALSE);

    foreach ($criteria as $field => $value) {
      $query->condition($field, $value);
    }

    $ids = $query->range(0, 1)->execute();

    if (empty($ids)) {
      return NULL;
    }

    return $storage->load(reset($ids));
  }
}
