<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Creates or updates entities from webhook payload values.
 *
 * Uses identifier-flagged FieldMappings to find an existing entity via
 * EntityLookupService. If no match, a new entity is created. All
 * non-identifier (and identifier) field values are written to the entity.
 */
class EntityUpsertService implements EntityUpsertServiceInterface {
  /** Tracks whether the last upsert call created a new entity. */
  private bool $lastWasCreated = FALSE;

  /**
   * Constructs an EntityUpsertService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\entity_webhook\Service\EntityLookupServiceInterface $entityLookup
   *   The entity lookup service.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityLookupServiceInterface $entityLookup,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function upsert(
    string $entityTypeId,
    string $bundle,
    array $mappings,
    array $extractedValues,
  ): EntityInterface {
    $entity = $this->resolveEntityWithoutSave($entityTypeId, $bundle, $mappings, $extractedValues);

    $this->applyFieldValues($entity, $mappings, $extractedValues);
    $entity->save();

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function wasCreated(): bool {
    return $this->lastWasCreated;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveEntityWithoutSave(
    string $entityTypeId,
    string $bundle,
    array $mappings,
    array $extractedValues,
  ): EntityInterface {
    $identifierCriteria = $this->buildIdentifierCriteria($mappings, $extractedValues);

    if (!empty($identifierCriteria)) {
      $existing = $this->entityLookup->findEntity($entityTypeId, $identifierCriteria);
      if ($existing !== NULL) {
        $this->lastWasCreated = FALSE;

        return $existing;
      }
    }

    $this->lastWasCreated = TRUE;

    return $this->createEntity($entityTypeId, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function applyFieldValues(
    EntityInterface $entity,
    array $mappings,
    array $extractedValues,
  ): void {
    $this->applyMappings($entity, $mappings, $extractedValues);
  }

  /**
   * Builds the identifier criteria array from identifier-flagged mappings.
   *
   * @param \Drupal\entity_webhook\Entity\FieldMapping[] $mappings
   *   All field mappings.
   * @param array<string, mixed> $extractedValues
   *   Extracted payload values keyed by entity field.
   *
   * @return array<string, string>
   *   Field => value pairs for identifier lookup.
   */
  private function buildIdentifierCriteria(array $mappings, array $extractedValues): array {
    $criteria = [];

    foreach ($mappings as $mapping) {
      if (!$mapping->isIdentifier) {
        continue;
      }
      if (!isset($extractedValues[$mapping->entityField])) {
        continue;
      }
      $criteria[$mapping->entityField] = (string) $extractedValues[$mapping->entityField];
    }

    return $criteria;
  }

  /**
   * Creates a new entity of the given type and bundle.
   *
   * @param string $entityTypeId
   *   The entity type machine name.
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A new unsaved entity instance.
   */
  private function createEntity(string $entityTypeId, string $bundle): EntityInterface {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $bundleKey = $entityType->getKey('bundle');

    $values = $bundleKey ? [$bundleKey => $bundle] : [];

    return $storage->create($values);
  }

  /**
   * Applies all field mappings to the entity from the extracted values.
   *
   * Multi-value fields (arrays) are set directly; scalar values are wrapped
   * as needed by the Drupal field API set() call. Only FieldableEntityInterface
   * instances support set(); non-fieldable entities are skipped silently.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to populate.
   * @param \Drupal\entity_webhook\Entity\FieldMapping[] $mappings
   *   All field mappings.
   * @param array<string, mixed> $extractedValues
   *   Extracted payload values keyed by entity field.
   */
  private function applyMappings(
    EntityInterface $entity,
    array $mappings,
    array $extractedValues,
  ): void {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    foreach ($mappings as $mapping) {
      if (!isset($extractedValues[$mapping->entityField])) {
        continue;
      }

      if ($this->isEntityIdField($entity, $mapping->entityField)) {
        continue;
      }

      $entity->set($mapping->entityField, $extractedValues[$mapping->entityField]);
    }
  }

  /**
   * Returns whether the given field is a structural key managed by Drupal.
   *
   * Entity ID and bundle fields must not be set via set() after creation,
   * as they are managed by the entity system.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param string $fieldName
   *   The field machine name.
   *
   * @return bool
   *   TRUE if this field is an entity ID or bundle key.
   */
  private function isEntityIdField(EntityInterface $entity, string $fieldName): bool {
    $entityType = $entity->getEntityType();
    $idKey = $entityType->getKey('id');
    $bundleKey = $entityType->getKey('bundle');

    return $fieldName === $idKey || ($bundleKey && $fieldName === $bundleKey);
  }
}
