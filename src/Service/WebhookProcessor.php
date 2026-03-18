<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook\Entity\FieldMapping;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\entity_webhook\Event\EntityWebhookEvents;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Event\EntityWebhookPreSaveEvent;
use Drupal\entity_webhook\Event\EntityWebhookPostSaveEvent;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\entity_webhook\Validator\WebhookRequestValidatorInterface;
use Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverManagerInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;

/**
 * Orchestrates the full entity upsert pipeline for a single queue item.
 *
 * Loads WebhookEndpoint and WebhookSourceType config, extracts field values
 * from the payload using pluggable value resolvers and optional mutations,
 * dispatches PreSave/PostSave events, and delegates to EntityUpsertService.
 * Failures are logged and skipped — no exceptions propagate to the caller.
 */
class WebhookProcessor implements WebhookProcessorInterface {
  /**
   * Constructs a WebhookProcessor.
   *
   * @param \Drupal\entity_webhook\Validator\WebhookRequestValidatorInterface $validator
   *   The webhook request validator used to load config entities.
   * @param \Drupal\entity_webhook\Service\EntityUpsertServiceInterface $entityUpsert
   *   The entity upsert service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel for entity_webhook.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $mutationManager
   *   The field value mutation plugin manager.
   * @param \Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverManagerInterface $resolverManager
   *   The value resolver plugin manager.
   */
  public function __construct(
    protected readonly WebhookRequestValidatorInterface $validator,
    protected readonly EntityUpsertServiceInterface $entityUpsert,
    protected readonly LoggerChannelInterface $logger,
    protected readonly EventDispatcherInterface $eventDispatcher,
    protected readonly FieldValueMutationManagerInterface $mutationManager,
    protected readonly ValueResolverManagerInterface $resolverManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function process(WebhookQueueItem $item): void {
    $endpoint = $this->loadEndpoint($item->endpointId);

    if ($endpoint === NULL) {
      return;
    }

    $sourceType = $this->loadSourceType($item->sourceType);

    if ($sourceType === NULL) {
      return;
    }

    if (!$this->validateSourceTypeAssociation($endpoint, $item)) {
      return;
    }

    try {
      $this->processItem($endpoint, $sourceType, $item);
    } catch (\Throwable $e) {
      $this->logProcessingError($item, $e);
    }
  }

  /**
   * Checks that the source type is associated with the endpoint.
   *
   * Logs a warning and returns FALSE when the association is missing.
   *
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint
   *   The loaded endpoint config entity.
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item being processed.
   *
   * @return bool
   *   TRUE when the source type is associated; FALSE otherwise.
   */
  private function validateSourceTypeAssociation(WebhookEndpointInterface $endpoint, WebhookQueueItem $item): bool {
    if ($endpoint->hasSourceType($item->sourceType)) {
      return TRUE;
    }

    $this->logger->warning(
      'Source type @source not associated with endpoint @endpoint.',
      [
        '@source' => $item->sourceType,
        '@endpoint' => $item->endpointId,
      ],
    );

    return FALSE;
  }

  /**
   * Orchestrates the happy-path entity upsert pipeline for a single item.
   *
   * Extracts field values, resolves/creates the entity, applies values,
   * dispatches PreSave/PostSave events, and saves the entity.
   *
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint
   *   The loaded endpoint config entity.
   * @param \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $sourceType
   *   The loaded source type config entity.
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item being processed.
   */
  private function processItem(WebhookEndpointInterface $endpoint, WebhookSourceTypeInterface $sourceType, WebhookQueueItem $item): void {
    $mappings = $sourceType->getFieldMappings();
    $extractedValues = $this->extractFieldValues($mappings, $item->payload);

    $entity = $this->entityUpsert->resolveEntity(
      $endpoint->getTargetEntityTypeId(),
      $endpoint->getTargetEntityBundle(),
      $mappings,
      $extractedValues,
    );

    $this->entityUpsert->applyFieldValues($entity, $mappings, $extractedValues);

    $preSaveEvent = $this->dispatchPreSaveEvent($entity, $item);

    if ($preSaveEvent->isAborted()) {
      $this->logger->info(
        'Webhook processing aborted by PreSave event subscriber for endpoint @endpoint.',
        ['@endpoint' => $item->endpointId],
      );

      return;
    }

    $wasCreated = $entity->isNew();
    $entity->save();

    $this->dispatchPostSaveEvent($entity, $item, $wasCreated);

    $this->logger->info('Processed webhook item for endpoint @endpoint, source @source.', [
      '@endpoint' => $item->endpointId,
      '@source' => $item->sourceType,
    ]);
  }

  /**
   * Creates and dispatches the PreSave event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity about to be saved.
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item being processed.
   *
   * @return \Drupal\entity_webhook\Event\EntityWebhookPreSaveEvent
   *   The dispatched event, possibly modified by subscribers.
   */
  private function dispatchPreSaveEvent(EntityInterface $entity, WebhookQueueItem $item): EntityWebhookPreSaveEvent {
    return $this->eventDispatcher->dispatch(
      new EntityWebhookPreSaveEvent(
        entity: $entity,
        payload: $item->payload,
        endpointId: $item->endpointId,
        sourceType: $item->sourceType,
        isNew: $entity->isNew(),
      ),
      EntityWebhookEvents::PRE_SAVE,
    );
  }

  /**
   * Creates and dispatches the PostSave event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was saved.
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item being processed.
   * @param bool $wasCreated
   *   TRUE if the entity was newly created, FALSE if it was updated.
   */
  private function dispatchPostSaveEvent(EntityInterface $entity, WebhookQueueItem $item, bool $wasCreated): void {
    $this->eventDispatcher->dispatch(
      new EntityWebhookPostSaveEvent(
        entity: $entity,
        payload: $item->payload,
        endpointId: $item->endpointId,
        sourceType: $item->sourceType,
        wasCreated: $wasCreated,
      ),
      EntityWebhookEvents::POST_SAVE,
    );
  }

  /**
   * Logs an error when an exception escapes the processing pipeline.
   *
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item that failed.
   * @param \Throwable $e
   *   The caught exception or error.
   */
  private function logProcessingError(WebhookQueueItem $item, \Throwable $e): void {
    $this->logger->error('Failed to process webhook item for endpoint @endpoint: @message', [
      '@endpoint' => $item->endpointId,
      '@message' => $e->getMessage(),
    ]);
  }

  /**
   * Loads the WebhookEndpoint config entity, logging a warning if missing.
   *
   * @param string $endpointId
   *   The endpoint machine name.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null
   *   The loaded endpoint, or NULL.
   */
  private function loadEndpoint(string $endpointId): ?WebhookEndpointInterface {
    $endpoint = $this->validator->loadEndpoint($endpointId);

    if ($endpoint === NULL) {
      $this->logger->warning(
        'Webhook endpoint @id was not found, skipping the queue item.',
        [
          '@id' => $endpointId,
        ],
      );
    }

    return $endpoint;
  }

  /**
   * Loads the WebhookSourceType config entity, logging a warning if missing.
   *
   * @param string $sourceTypeId
   *   The source type machine name.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface|null
   *   The loaded source type, or NULL.
   */
  private function loadSourceType(string $sourceTypeId): ?WebhookSourceTypeInterface {
    $sourceType = $this->validator->loadSourceType($sourceTypeId);

    if ($sourceType === NULL) {
      $this->logger->warning(
        'Webhook source type @id was not found, skipping the queue item.',
        [
          '@id' => $sourceTypeId,
        ],
      );
    }

    return $sourceType;
  }

  /**
   * Extracts field values from the payload using each mapping's resolver.
   *
   * For each field mapping, instantiates the configured value resolver plugin
   * to extract the value. Applies the optional mutation plugin after extraction.
   *
   * @param \Drupal\entity_webhook\Entity\FieldMapping[] $mappings
   *   All field mappings for the source type.
   * @param array<string, mixed> $payload
   *   The decoded JSON payload.
   *
   * @return array<string, mixed>
   *   Extracted values keyed by entity field machine name.
   */
  private function extractFieldValues(array $mappings, array $payload): array {
    $values = [];

    foreach ($mappings as $mapping) {
      $extracted = $this->resolveValue($mapping, $payload);

      if ($extracted !== NULL) {
        $extracted = $this->applyMutation($mapping, $extracted);
        $values[$mapping->entityField] = $extracted;
      }
    }

    return $values;
  }

  /**
   * Resolves a field value from the payload using the mapping's resolver.
   *
   * @param \Drupal\entity_webhook\Entity\FieldMapping $mapping
   *   The field mapping with resolver configuration.
   * @param array<string, mixed> $payload
   *   The decoded JSON payload.
   *
   * @return mixed
   *   The resolved value, or NULL on failure.
   */
  private function resolveValue(FieldMapping $mapping, array $payload): mixed {
    try {
      $resolver = $this->resolverManager->createInstance(
        $mapping->resolver,
        $mapping->resolverConfig,
      );

      return $resolver->resolve($payload);
    } catch (\Throwable $e) {
      $this->logger->error(
        'Value resolver plugin @plugin failed for field @field: @message',
        [
          '@plugin' => $mapping->resolver,
          '@field' => $mapping->entityField,
          '@message' => $e->getMessage(),
        ],
      );

      return NULL;
    }
  }

  /**
   * Applies a mutation plugin to the extracted value, if one is configured.
   *
   * Returns the original value unchanged when no mutation plugin is set.
   * On plugin instantiation or mutation failure the error is logged and the
   * original value is returned so downstream processing is not disrupted.
   *
   * @param \Drupal\entity_webhook\Entity\FieldMapping $mapping
   *   The field mapping that may carry a mutation plugin ID and config.
   * @param mixed $value
   *   The extracted field value to potentially transform.
   *
   * @return mixed
   *   The mutated value, or the original value when no mutation is configured
   *   or when mutation fails.
   */
  private function applyMutation(FieldMapping $mapping, mixed $value): mixed {
    if ($mapping->mutationPlugin === '') {
      return $value;
    }

    try {
      $plugin = $this->mutationManager->createInstance(
        $mapping->mutationPlugin,
        $mapping->mutationConfig,
      );

      return $plugin->mutate($value);
    } catch (\Throwable $e) {
      $this->logger->error(
        'Field value mutation plugin @plugin failed for field @field: @message',
        [
          '@plugin' => $mapping->mutationPlugin,
          '@field' => $mapping->entityField,
          '@message' => $e->getMessage(),
        ],
      );

      return $value;
    }
  }
}
