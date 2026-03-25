<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

use Drupal\Core\Condition\ConditionAccessResolverTrait;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLog;
use Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem;
use Drupal\entity_webhook_broadcast\Queue\OutboundQueueServiceInterface;

/**
 * Dispatches entity CRUD events to matching outbound webhook subscriptions.
 *
 * Loads enabled OutboundEndpoint configs that match the entity type, bundle,
 * and event, then iterates their active subscriptions. For each matching
 * subscription the payload is built eagerly and an OutboundQueueItem is
 * enqueued for async delivery.
 */
class OutboundDispatcher implements OutboundDispatcherInterface {

  use ConditionAccessResolverTrait;

  /**
   * Constructs an OutboundDispatcher.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager for loading config entities.
   * @param \Drupal\entity_webhook_broadcast\Service\PayloadBuilderInterface $payloadBuilder
   *   The payload builder service.
   * @param \Drupal\entity_webhook_broadcast\Queue\OutboundQueueServiceInterface $queueService
   *   The outbound queue service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PayloadBuilderInterface $payloadBuilder,
    private readonly OutboundQueueServiceInterface $queueService,
    private readonly LoggerChannelInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function dispatch(EntityInterface $entity, string $event): void {
    $endpoints = $this->loadMatchingEndpoints($entity, $event);

    foreach ($endpoints as $endpoint) {
      $this->dispatchToEndpoint($entity, $event, $endpoint);
    }
  }

  /**
   * Loads enabled OutboundEndpoint configs matching the entity and event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The triggering entity.
   * @param string $event
   *   The CRUD event name.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface[]
   *   Matching enabled endpoints.
   */
  private function loadMatchingEndpoints(EntityInterface $entity, string $event): array {
    $entityTypeId = $entity->getEntityTypeId();
    $storage = $this->entityTypeManager->getStorage('outbound_endpoint');

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', TRUE)
      ->condition('entity_type', $entityTypeId)
      ->execute();

    if (empty($ids)) {
      return [];
    }

    $endpoints = $storage->loadMultiple($ids);

    return array_filter(
      $endpoints,
      fn($ep) => $ep instanceof OutboundEndpointInterface && $this->endpointMatchesEntity($ep, $entity, $event),
    );
  }

  /**
   * Returns TRUE if the endpoint matches the entity bundle and event.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $endpoint
   *   The endpoint to check.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The triggering entity.
   * @param string $event
   *   The CRUD event name.
   *
   * @return bool
   *   TRUE if the endpoint should handle this entity event.
   */
  private function endpointMatchesEntity(
    OutboundEndpointInterface $endpoint,
    EntityInterface $entity,
    string $event,
  ): bool {
    $bundleFilter = $endpoint->getEntityBundle();
    if ($bundleFilter !== NULL && $bundleFilter !== $entity->bundle()) {
      return FALSE;
    }

    if (!in_array($event, $endpoint->getEvents(), TRUE)) {
      return FALSE;
    }

    return $this->evaluateConditions($endpoint, $entity);
  }

  /**
   * Evaluates all active conditions on an endpoint against the given entity.
   *
   * Returns TRUE immediately when no conditions are actively configured.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $endpoint
   *   The endpoint whose conditions to evaluate.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to evaluate conditions against.
   *
   * @return bool
   *   TRUE if all conditions pass (AND logic), FALSE otherwise.
   */
  private function evaluateConditions(OutboundEndpointInterface $endpoint, EntityInterface $entity): bool
  {
    $activeConditions = $endpoint->getActiveConditions();

    if ($activeConditions === []) {
      return TRUE;
    }

    $context = new Context(
      EntityContextDefinition::fromEntityType($entity->getEntityType()),
      $entity,
    );

    $collection = $endpoint->getConditions();
    $instances = [];

    foreach (array_keys($activeConditions) as $conditionId) {
      if ($collection->has($conditionId)) {
        $condition = $collection->get($conditionId);
        $this->applyEntityContext($condition, $context);
        $instances[$conditionId] = $condition;
      }
    }

    if ($instances === []) {
      return TRUE;
    }

    return $this->resolveConditions($instances, 'and');
  }

  /**
   * Sets the entity context on a condition using its declared context keys.
   *
   * Condition plugins declare context requirements with varying keys: generic
   * conditions use 'entity', while entity_bundle derivatives use the entity
   * type ID (e.g. 'node', 'user'). This method inspects the condition's
   * context definitions and sets the entity context on every slot that accepts
   * an entity data type.
   *
   * @param \Drupal\Core\Condition\ConditionInterface $condition
   *   The condition plugin instance.
   * @param \Drupal\Core\Plugin\Context\Context $context
   *   The entity context to assign.
   */
  private function applyEntityContext(ConditionInterface $condition, Context $context): void {
    foreach ($condition->getContextDefinitions() as $contextName => $definition) {
      $dataType = $definition->getDataType();
      if ($dataType === 'entity' || str_starts_with($dataType, 'entity:')) {
        $condition->setContext($contextName, $context);
      }
    }
  }

  /**
   * Dispatches a single entity event to all active subscriptions of an endpoint.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The triggering entity.
   * @param string $event
   *   The CRUD event name.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $endpoint
   *   The matching endpoint.
   */
  private function dispatchToEndpoint(
    EntityInterface $entity,
    string $event,
    OutboundEndpointInterface $endpoint,
  ): void {
    $subscriptions = $this->loadActiveSubscriptions($endpoint->id() ?? '');

    foreach ($subscriptions as $subscription) {
      $this->enqueueForSubscription($entity, $event, $endpoint, $subscription);
    }
  }

  /**
   * Loads active OutboundSubscription entities for the given endpoint.
   *
   * @param string $endpointId
   *   The endpoint ID.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface[]
   *   Active subscriptions for the endpoint.
   */
  private function loadActiveSubscriptions(string $endpointId): array {
    if ($endpointId === '') {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('outbound_subscription');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('endpoint_id', $endpointId)
      ->condition('active', TRUE)
      ->execute();

    if (empty($ids)) {
      return [];
    }

    return array_filter(
      $storage->loadMultiple($ids),
      fn($sub) => $sub instanceof OutboundSubscriptionInterface,
    );
  }

  /**
   * Builds the payload and enqueues a delivery item for one subscription.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The triggering entity.
   * @param string $event
   *   The CRUD event name.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $endpoint
   *   The matching endpoint.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The target subscription.
   */
  private function enqueueForSubscription(
    EntityInterface $entity,
    string $event,
    OutboundEndpointInterface $endpoint,
    OutboundSubscriptionInterface $subscription,
  ): void {
    try {
      $payload = $this->payloadBuilder->build($entity, $subscription);
      $log = $this->createDeliveryLog($entity, $event, $subscription, $payload);

      $item = new OutboundQueueItem(
        endpointId: $endpoint->id() ?? '',
        subscriptionId: $subscription->id() ?? '',
        entityTypeId: $entity->getEntityTypeId(),
        entityId: (string) $entity->id(),
        event: $event,
        payload: $payload,
        deliveryLogId: $log !== NULL ? (string) $log->id() : NULL,
        attempt: 1,
        dispatchedAt: new \DateTimeImmutable(),
      );

      $this->queueService->enqueue($item);
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Failed to enqueue outbound webhook for subscription @sub on @event event for @entity_type:@entity_id: @message',
        [
          '@sub' => $subscription->id(),
          '@event' => $event,
          '@entity_type' => $entity->getEntityTypeId(),
          '@entity_id' => $entity->id(),
          '@message' => $e->getMessage(),
        ],
      );
    }
  }

  /**
   * Creates an OutboundDeliveryLog entity with status 'pending'.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The triggering entity.
   * @param string $event
   *   The CRUD event name.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The target subscription.
   * @param array<string, mixed> $payload
   *   The built payload.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface|null
   *   The saved delivery log, or NULL if creation failed.
   */
  private function createDeliveryLog(
    EntityInterface $entity,
    string $event,
    OutboundSubscriptionInterface $subscription,
    array $payload,
  ): ?OutboundDeliveryLogInterface {
    try {
      $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
      $payloadHash = hash('sha256', $payloadJson);

      $log = OutboundDeliveryLog::create([
        'subscription' => $subscription->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => (string) $entity->id(),
        'event' => $event,
        'payload_hash' => $payloadHash,
        'payload' => $payloadJson,
        'attempt' => 1,
        'max_attempts' => $subscription->getRetryMaxAttempts(),
        'status' => 'pending',
      ]);

      $log->save();

      return $log instanceof OutboundDeliveryLogInterface ? $log : NULL;
    }
    catch (\Throwable $e) {
      $this->logger->warning(
        'Failed to create delivery log for subscription @sub: @message',
        [
          '@sub' => $subscription->id(),
          '@message' => $e->getMessage(),
        ],
      );
      return NULL;
    }
  }

}
