<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after all sub-payloads from a batch processor are handled.
 *
 * This event fires only when a WebhookSourceType has a payload_processor
 * configured. It carries the original, unmodified payload along with the
 * results from processing each derived sub-payload, giving subscribers a
 * complete picture of the batch run so they can perform post-batch logic such
 * as deletion of stale records.
 */
class WebhookBatchCompleteEvent extends Event {
  /**
   * Constructs a WebhookBatchCompleteEvent.
   *
   * @param array<string, mixed> $originalPayload
   *   The raw payload as received before the processor split it.
   * @param string $sourceType
   *   The WebhookSourceType config entity ID.
   * @param string $endpointId
   *   The WebhookEndpoint config entity ID.
   * @param \Drupal\entity_webhook\Service\WebhookProcessResult[] $results
   *   The ordered results from processing each derived sub-payload.
   */
  public function __construct(
    public readonly array $originalPayload,
    public readonly string $sourceType,
    public readonly string $endpointId,
    public readonly array $results,
  ) {
  }

}
