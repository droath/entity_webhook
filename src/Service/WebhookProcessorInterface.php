<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Drupal\entity_webhook\Queue\WebhookQueueItem;

/**
 * Defines the interface for the webhook processor service.
 *
 * Orchestrates the full processing pipeline for a single queue item:
 * load endpoint and source type config, extract values via JSONPath,
 * then upsert the target entity.
 */
interface WebhookProcessorInterface {
  /**
   * Processes a single webhook queue item.
   *
   * Loads the endpoint and source type config entities, extracts field values
   * from the payload using JSONPath expressions, and upserts the target entity.
   * Logs a warning and returns without throwing on any recoverable failure.
   *
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item to process.
   */
  public function process(WebhookQueueItem $item): void;
}
