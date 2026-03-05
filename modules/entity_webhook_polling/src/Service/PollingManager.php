<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\entity_webhook\Queue\WebhookQueueServiceInterface;
use Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface;
use Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderManagerInterface;
use Drupal\entity_webhook_polling\Storage\PollingStateStorageInterface;

/**
 * Orchestrates polling execution for all enabled polling configurations.
 *
 * For each enabled EntityWebhookPolling whose cron expression is due:
 *   1. Instantiates the configured polling provider plugin.
 *   2. Calls provider->fetch() to retrieve an array of payloads.
 *   3. For each payload, computes a SHA-256 hash of the payload content.
 *   4. Compares the hash against the stored value in PollingStateStorage.
 *   5. If new or changed, queues the payload via WebhookQueueService with
 *      source='polling' and updates the stored hash.
 */
class PollingManager implements PollingManagerInterface {

  /**
   * Constructs a new PollingManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderManagerInterface $providerManager
   *   The polling provider plugin manager.
   * @param \Drupal\entity_webhook_polling\Storage\PollingStateStorageInterface $stateStorage
   *   The polling state storage service.
   * @param \Drupal\entity_webhook\Queue\WebhookQueueServiceInterface $queueService
   *   The webhook queue service from the parent module.
   * @param \Drupal\entity_webhook_polling\Service\CronExpressionServiceInterface $cronExpressionService
   *   The cron expression evaluation service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PollingProviderManagerInterface $providerManager,
    protected readonly PollingStateStorageInterface $stateStorage,
    protected readonly WebhookQueueServiceInterface $queueService,
    protected readonly CronExpressionServiceInterface $cronExpressionService,
    protected readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function runAllDue(): void {
    $storage = $this->entityTypeManager->getStorage('entity_webhook_polling');
    /** @var \Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface[] $configs */
    $configs = $storage->loadByProperties(['status' => TRUE]);

    foreach ($configs as $config) {
      if ($this->cronExpressionService->isDue($config->getCronExpression())) {
        $this->runPolling($config->id());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function runPolling(string $pollingId): void {
    /** @var \Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface|null $config */
    $config = $this->entityTypeManager
      ->getStorage('entity_webhook_polling')
      ->load($pollingId);

    if ($config === NULL || !$config->status()) {
      return;
    }

    try {
      $provider = $this->providerManager->createInstance(
        $config->getPollingProvider(),
        $config->getPollingProviderConfig(),
      );

      $payloads = $provider->fetch();

      foreach ($payloads as $payload) {
        $this->processPayload($config, $payload);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Polling failed for @id: @message', [
        '@id' => $pollingId,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Computes the hash for a payload and queues it if new or changed.
   *
   * The external ID is derived from the payload's 'id' key. If absent, the
   * hash of the full payload is used as the external ID so that payloads
   * without explicit identifiers are still deduplicated correctly.
   *
   * @param \Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface $config
   *   The polling configuration entity.
   * @param array<string, mixed> $payload
   *   The payload returned by the provider.
   */
  private function processPayload(EntityWebhookPollingInterface $config, array $payload): void {
    $hash = hash('sha256', serialize($payload));
    $externalId = isset($payload['id']) ? (string) $payload['id'] : $hash;

    $storedHash = $this->stateStorage->getHash($config->id(), $externalId);

    if ($storedHash === $hash) {
      return;
    }

    $item = new WebhookQueueItem(
      endpointId: $config->getEndpointId(),
      sourceType: $config->getSourceTypeId(),
      payload: $payload,
      receivedAt: new \DateTimeImmutable(),
      source: 'polling',
    );

    $this->queueService->enqueue($item);
    $this->stateStorage->saveState($config->id(), $externalId, $hash);
  }

}
