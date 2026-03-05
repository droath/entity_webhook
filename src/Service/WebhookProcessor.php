<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_webhook\Event\EntityWebhookEvents;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Event\EntityWebhookPreSaveEvent;
use Drupal\entity_webhook\Event\EntityWebhookPostSaveEvent;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Orchestrates the full entity upsert pipeline for a single queue item.
 *
 * Loads WebhookEndpoint and WebhookSourceType config, extracts field values
 * from the payload using JSONPath, dispatches PreSave/PostSave events, and
 * delegates to EntityUpsertService. Failures are logged and skipped — no
 * exceptions propagate to the caller.
 */
class WebhookProcessor implements WebhookProcessorInterface {
  /**
   * Constructs a WebhookProcessor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\entity_webhook\Service\JsonPathExtractorInterface $jsonPathExtractor
   *   The JSONPath extractor service.
   * @param \Drupal\entity_webhook\Service\EntityUpsertServiceInterface $entityUpsert
   *   The entity upsert service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel for entity_webhook.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly JsonPathExtractorInterface $jsonPathExtractor,
    protected readonly EntityUpsertServiceInterface $entityUpsert,
    protected readonly LoggerChannelInterface $logger,
    protected readonly EventDispatcherInterface $eventDispatcher,
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

    if (!$endpoint->hasSourceType($item->sourceType)) {
      $this->logger->warning('Source type @source not associated with endpoint @endpoint.', [
        '@source' => $item->sourceType,
        '@endpoint' => $item->endpointId,
      ]);

      return;
    }

    try {
      $extractedValues = $this->extractFieldValues($sourceType->getFieldMappings(), $item->payload);

      $entity = $this->entityUpsert->resolveEntityWithoutSave(
        $endpoint->getTargetEntityTypeId(),
        $this->resolveBundle($endpoint->getTargetEntityTypeId(), $extractedValues),
        $sourceType->getFieldMappings(),
        $extractedValues,
      );

      $isNew = $entity->isNew();

      $this->entityUpsert->applyFieldValues($entity, $sourceType->getFieldMappings(), $extractedValues);

      $preSaveEvent = new EntityWebhookPreSaveEvent(
        entity: $entity,
        payload: $item->payload,
        endpointId: $item->endpointId,
        sourceType: $item->sourceType,
        isNew: $isNew,
      );

      $this->eventDispatcher->dispatch($preSaveEvent, EntityWebhookEvents::PRE_SAVE);

      if ($preSaveEvent->isAborted()) {
        $this->logger->info('Webhook processing aborted by PreSave event subscriber for endpoint @endpoint.', [
          '@endpoint' => $item->endpointId,
        ]);

        return;
      }

      $entity->save();

      $this->eventDispatcher->dispatch(
        new EntityWebhookPostSaveEvent(
          entity: $entity,
          payload: $item->payload,
          endpointId: $item->endpointId,
          sourceType: $item->sourceType,
          wasCreated: $isNew,
        ),
        EntityWebhookEvents::POST_SAVE,
      );

      $this->logger->info('Processed webhook item for endpoint @endpoint, source @source.', [
        '@endpoint' => $item->endpointId,
        '@source' => $item->sourceType,
      ]);

    } catch (\Throwable $e) {
      $this->logger->error('Failed to process webhook item for endpoint @endpoint: @message', [
        '@endpoint' => $item->endpointId,
        '@message' => $e->getMessage(),
      ]);
    }
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
    $endpoint = $this->entityTypeManager
      ->getStorage('webhook_endpoint')
      ->load($endpointId);

    if (!$endpoint instanceof WebhookEndpointInterface) {
      $this->logger->warning('Webhook endpoint @id not found; skipping queue item.', [
        '@id' => $endpointId,
      ]);

      return NULL;
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
    $sourceType = $this->entityTypeManager
      ->getStorage('webhook_source_type')
      ->load($sourceTypeId);

    if (!$sourceType instanceof WebhookSourceTypeInterface) {
      $this->logger->warning('Webhook source type @id not found; skipping queue item.', [
        '@id' => $sourceTypeId,
      ]);

      return NULL;
    }

    return $sourceType;
  }

  /**
   * Extracts field values from the payload using each mapping's JSONPath.
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
      $extracted = $this->jsonPathExtractor->extract($payload, $mapping->jsonPath);
      if ($extracted !== NULL) {
        $values[$mapping->entityField] = $extracted;
      }
    }

    return $values;
  }

  /**
   * Resolves the bundle for the entity type from extracted values.
   *
   * Falls back to an empty string when no bundle key is present in the
   * extracted values, allowing the upsert service to handle bundle-less
   * entity types gracefully.
   *
   * @param string $entityTypeId
   *   The entity type machine name.
   * @param array<string, mixed> $extractedValues
   *   Extracted payload values keyed by entity field.
   *
   * @return string
   *   The bundle machine name, or an empty string.
   */
  private function resolveBundle(string $entityTypeId, array $extractedValues): string {
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $bundleKey = $entityType->getKey('bundle');

    if ($bundleKey && isset($extractedValues[$bundleKey])) {
      return (string) $extractedValues[$bundleKey];
    }

    return '';
  }
}
