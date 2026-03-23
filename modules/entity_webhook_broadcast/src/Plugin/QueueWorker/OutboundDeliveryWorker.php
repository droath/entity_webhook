<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem;
use Drupal\entity_webhook_broadcast\Service\DeliveryResult;
use Drupal\entity_webhook_broadcast\Service\DeliveryServiceInterface;
use Drupal\entity_webhook_broadcast\Service\RetrySchedulerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued outbound webhook delivery items.
 *
 * Each item is deserialized from a plain array into an OutboundQueueItem,
 * the target subscription is loaded, and the payload is delivered via
 * DeliveryService. On success the associated DeliveryLog is updated to
 * 'success'. On failure the attempt is recorded and the retry scheduler
 * determines whether to schedule another attempt or mark as 'abandoned'.
 */
#[QueueWorker(
  id: 'entity_webhook_broadcast',
  title: new TranslatableMarkup('Entity Webhook Broadcast Delivery'),
  cron: ['time' => 60],
)]
class OutboundDeliveryWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an OutboundDeliveryWorker.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\entity_webhook_broadcast\Service\DeliveryServiceInterface $deliveryService
   *   The delivery service.
   * @param \Drupal\entity_webhook_broadcast\Service\RetrySchedulerInterface $retryScheduler
   *   The retry scheduler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager for loading subscriptions and delivery logs.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly DeliveryServiceInterface $deliveryService,
    private readonly RetrySchedulerInterface $retryScheduler,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_webhook_broadcast.delivery_service'),
      $container->get('entity_webhook_broadcast.retry_scheduler'),
      $container->get('entity_type.manager'),
      $container->get('logger.channel.entity_webhook_broadcast'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $data
   *   The raw queue item data (plain array from OutboundQueueItem::toArray()).
   */
  public function processItem($data): void {
    $item = OutboundQueueItem::fromArray((array) $data);
    $subscription = $this->loadSubscription($item->subscriptionId);

    if ($subscription === NULL) {
      $this->logger->warning(
        'Outbound delivery skipped: subscription @id not found.',
        ['@id' => $item->subscriptionId],
      );
      return;
    }

    $result = $this->deliveryService->deliver($subscription, $item->payload);
    $log = $this->loadDeliveryLog($item->deliveryLogId);

    if ($result->success) {
      $this->handleSuccess($result, $item, $log);
      return;
    }

    $this->handleFailure($result, $item, $subscription, $log);
  }

  /**
   * Handles a successful delivery by updating the log and recording outcome.
   *
   * @param \Drupal\entity_webhook_broadcast\Service\DeliveryResult $result
   *   The successful delivery result.
   * @param \Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem $item
   *   The processed queue item.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface|null $log
   *   The delivery log entry, if available.
   */
  private function handleSuccess(
    DeliveryResult $result,
    OutboundQueueItem $item,
    ?OutboundDeliveryLogInterface $log,
  ): void {
    $this->updateLog($log, 'success', $result);

    $this->logger->info(
      'Outbound webhook delivered (HTTP @status) for @event on @entity_type:@entity_id via subscription @sub.',
      [
        '@status' => $result->httpStatus,
        '@event' => $item->event,
        '@entity_type' => $item->entityTypeId,
        '@entity_id' => $item->entityId,
        '@sub' => $item->subscriptionId,
      ],
    );
  }

  /**
   * Handles a failed delivery by scheduling retry or marking as abandoned.
   *
   * @param \Drupal\entity_webhook_broadcast\Service\DeliveryResult $result
   *   The failed delivery result.
   * @param \Drupal\entity_webhook_broadcast\Queue\OutboundQueueItem $item
   *   The processed queue item.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The subscription providing the retry policy.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface|null $log
   *   The delivery log entry, if available.
   */
  private function handleFailure(
    DeliveryResult $result,
    OutboundQueueItem $item,
    OutboundSubscriptionInterface $subscription,
    ?OutboundDeliveryLogInterface $log,
  ): void {
    $maxAttempts = $subscription->getRetryMaxAttempts();

    if ($this->retryScheduler->shouldRetry($item->attempt, $maxAttempts)) {
      $retryAt = $this->retryScheduler->nextRetryAt(
        $item->attempt,
        $subscription->getRetryBaseDelay(),
      );
      $this->updateLogForRetry($log, $result, $retryAt);

      $this->logger->warning(
        'Outbound webhook delivery failed (attempt @attempt/@max) for @event on @entity_type:@entity_id. Retry at @retry.',
        [
          '@attempt' => $item->attempt,
          '@max' => $maxAttempts,
          '@event' => $item->event,
          '@entity_type' => $item->entityTypeId,
          '@entity_id' => $item->entityId,
          '@retry' => $retryAt->format('Y-m-d H:i:s'),
        ],
      );
      return;
    }

    $this->updateLog($log, 'abandoned', $result);

    $this->logger->error(
      'Outbound webhook delivery abandoned after @max attempts for @event on @entity_type:@entity_id: @error',
      [
        '@max' => $maxAttempts,
        '@event' => $item->event,
        '@entity_type' => $item->entityTypeId,
        '@entity_id' => $item->entityId,
        '@error' => $result->errorMessage,
      ],
    );
  }

  /**
   * Updates the delivery log with the final outcome of an attempt.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface|null $log
   *   The delivery log entry, or NULL if not found.
   * @param string $status
   *   The new status: 'success' or 'abandoned'.
   * @param \Drupal\entity_webhook_broadcast\Service\DeliveryResult $result
   *   The delivery result.
   */
  private function updateLog(
    ?OutboundDeliveryLogInterface $log,
    string $status,
    DeliveryResult $result,
  ): void {
    if ($log === NULL) {
      return;
    }

    $log->setStatus($status)
      ->setHttpStatus($result->httpStatus)
      ->setResponseBody($result->responseBody)
      ->setNextRetryAt(NULL)
      ->save();
  }

  /**
   * Updates the delivery log to schedule a retry attempt.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface|null $log
   *   The delivery log entry, or NULL if not found.
   * @param \Drupal\entity_webhook_broadcast\Service\DeliveryResult $result
   *   The delivery result.
   * @param \DateTimeImmutable $retryAt
   *   The time when the next retry should occur.
   */
  private function updateLogForRetry(
    ?OutboundDeliveryLogInterface $log,
    DeliveryResult $result,
    \DateTimeImmutable $retryAt,
  ): void {
    if ($log === NULL) {
      return;
    }

    $log->setStatus('pending')
      ->setHttpStatus($result->httpStatus)
      ->setResponseBody($result->responseBody)
      ->setNextRetryAt((int) $retryAt->getTimestamp())
      ->save();
  }

  /**
   * Loads an OutboundSubscription config entity by ID.
   *
   * @param string $subscriptionId
   *   The subscription ID.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null
   *   The subscription entity, or NULL if not found.
   */
  private function loadSubscription(string $subscriptionId): ?OutboundSubscriptionInterface {
    if ($subscriptionId === '') {
      return NULL;
    }

    $entity = $this->entityTypeManager
      ->getStorage('outbound_subscription')
      ->load($subscriptionId);

    return $entity instanceof OutboundSubscriptionInterface ? $entity : NULL;
  }

  /**
   * Loads an OutboundDeliveryLog content entity by ID.
   *
   * @param string|null $logId
   *   The delivery log entity ID, or NULL.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogInterface|null
   *   The delivery log entity, or NULL if not found or ID is empty.
   */
  private function loadDeliveryLog(?string $logId): ?OutboundDeliveryLogInterface {
    if ($logId === NULL || $logId === '') {
      return NULL;
    }

    $entity = $this->getDeliveryLogStorage()->load($logId);
    return $entity instanceof OutboundDeliveryLogInterface ? $entity : NULL;
  }

  /**
   * Returns the delivery log entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The storage handler.
   */
  private function getDeliveryLogStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('outbound_delivery_log');
  }

}
