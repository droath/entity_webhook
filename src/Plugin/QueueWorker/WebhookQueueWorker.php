<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_webhook\Service\WebhookProcessorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued webhook payloads by upserting the target entity.
 *
 * Each item is deserialized from a plain array into a WebhookQueueItem and
 * delegated to WebhookProcessorInterface. Failures are logged by the
 * processor; items are consumed regardless of outcome.
 */
#[QueueWorker(
  id: 'entity_webhook_processor',
  title: new TranslatableMarkup('Entity Webhook Processor'),
  cron: ['time' => 60],
)]
class WebhookQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * Constructs a WebhookQueueWorker.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\entity_webhook\Service\WebhookProcessorInterface $webhookProcessor
   *   The webhook processor service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly WebhookProcessorInterface $webhookProcessor,
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
      $container->get('entity_webhook.webhook_processor'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $data
   *   The raw queue item data (plain array from WebhookQueueItem::toArray()).
   */
  public function processItem($data): void {
    $item = WebhookQueueItem::fromArray((array) $data);
    $this->webhookProcessor->process($item);
  }
}
