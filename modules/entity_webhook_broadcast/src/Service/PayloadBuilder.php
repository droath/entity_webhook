<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface;

/**
 * Builds outbound webhook payloads by mapping entity values to output keys.
 *
 * Loads OutboundFieldMapping children for the given subscription, delegates
 * value resolution to the configured OutboundValueResolver plugin, applies any
 * configured FieldValueMutation plugin, and returns the structured payload
 * array keyed by output_key.
 */
class PayloadBuilder implements PayloadBuilderInterface {

  /**
   * Constructs a PayloadBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager for loading OutboundFieldMapping entities.
   * @param \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $mutationManager
   *   The parent module's field value mutation plugin manager.
   * @param \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $resolverManager
   *   The outbound value resolver plugin manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FieldValueMutationManagerInterface $mutationManager,
    private readonly OutboundValueResolverManagerInterface $resolverManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function build(EntityInterface $entity, OutboundSubscriptionInterface $subscription): array {
    $mappings = $this->loadFieldMappings($subscription->id() ?? '');
    $payload = [];

    foreach ($mappings as $mapping) {
      $value = $this->resolveValue($entity, $mapping);
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
   * Delegates value resolution to the configured OutboundValueResolver plugin.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The source entity.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $mapping
   *   The field mapping entity.
   *
   * @return mixed
   *   The resolved value.
   */
  private function resolveValue(EntityInterface $entity, OutboundFieldMappingInterface $mapping): mixed {
    $pluginId = $mapping->getResolver();

    if ($pluginId === '') {
      return NULL;
    }

    $resolver = $this->resolverManager->createInstance($pluginId, $mapping->getResolverConfig());

    return $resolver->resolve($entity);
  }

  /**
   * Applies the configured FieldValueMutation plugin to the value.
   *
   * @param mixed $value
   *   The resolved field value.
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
