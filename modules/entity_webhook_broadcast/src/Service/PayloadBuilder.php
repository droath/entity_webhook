<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;

/**
 * Builds outbound webhook payloads by mapping entity fields to output keys.
 *
 * Loads OutboundFieldMapping children for the given subscription, reads each
 * mapped entity field value, applies any configured FieldValueMutation plugin,
 * and returns the structured payload array keyed by output_key.
 */
class PayloadBuilder implements PayloadBuilderInterface {

  /**
   * Constructs a PayloadBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager for loading OutboundFieldMapping entities.
   * @param \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $mutationManager
   *   The parent module's field value mutation plugin manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FieldValueMutationManagerInterface $mutationManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function build(EntityInterface $entity, OutboundSubscriptionInterface $subscription): array {
    $mappings = $this->loadFieldMappings($subscription->id() ?? '');
    $payload = [];

    foreach ($mappings as $mapping) {
      $value = $this->extractFieldValue($entity, $mapping->getEntityField());
      $value = $this->applyMutation($value, $mapping);
      $payload[$mapping->getOutputKey()] = $value;
    }

    return $payload;
  }

  /**
   * Loads all OutboundFieldMapping entities for the given subscription.
   *
   * @param string $subscriptionId
   *   The subscription ID.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface[]
   *   The field mapping entities keyed by ID.
   */
  private function loadFieldMappings(string $subscriptionId): array {
    if ($subscriptionId === '') {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('outbound_field_mapping');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('subscription_id', $subscriptionId)
      ->execute();

    if (empty($ids)) {
      return [];
    }

    return array_filter(
      $storage->loadMultiple($ids),
      fn($entity) => $entity instanceof OutboundFieldMappingInterface,
    );
  }

  /**
   * Extracts a raw field value from the entity.
   *
   * For single-value fields the first item's raw value is returned. For
   * multi-value fields an array of raw values is returned. Fields that do
   * not exist on the entity return NULL.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The source entity.
   * @param string $fieldName
   *   The field machine name.
   *
   * @return mixed
   *   The raw field value.
   */
  private function extractFieldValue(EntityInterface $entity, string $fieldName): mixed {
    if (!$entity->hasField($fieldName)) {
      return NULL;
    }

    $fieldList = $entity->get($fieldName);
    $values = $fieldList->getValue();

    if (empty($values)) {
      return NULL;
    }

    if (count($values) === 1) {
      $first = reset($values);
      return is_array($first) && count($first) === 1 ? reset($first) : $first;
    }

    return array_map(fn($item) => is_array($item) && count($item) === 1 ? reset($item) : $item, $values);
  }

  /**
   * Applies the configured FieldValueMutation plugin to the value.
   *
   * @param mixed $value
   *   The extracted field value.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $mapping
   *   The field mapping entity.
   *
   * @return mixed
   *   The mutated value, or the original value if no plugin is configured.
   */
  private function applyMutation(mixed $value, OutboundFieldMappingInterface $mapping): mixed {
    $pluginId = $mapping->getMutationPlugin();

    if ($pluginId === '') {
      return $value;
    }

    $plugin = $this->mutationManager->createInstance($pluginId, $mapping->getMutationConfig());

    return $plugin->mutate($value);
  }

}
