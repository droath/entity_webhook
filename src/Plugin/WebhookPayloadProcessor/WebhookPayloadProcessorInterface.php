<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\WebhookPayloadProcessor;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines the interface for webhook payload processor plugins.
 *
 * Payload processor plugins split or transform a raw webhook payload into one
 * or more discrete payloads. Each returned payload is then handed to the
 * standard field-mapping pipeline independently, allowing a single webhook
 * request that carries multiple records to be fanned out into individual entity
 * upsert operations.
 */
interface WebhookPayloadProcessorInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {
  /**
   * Processes a raw webhook payload into one or more payloads.
   *
   * @param array<string, mixed> $payload
   *   The raw decoded webhook payload.
   * @param array<string, mixed> $config
   *   The plugin configuration for this invocation.
   *
   * @return array<int, array<string, mixed>>
   *   An indexed array of payloads to process individually. Returns an empty
   *   array when no processable items are found.
   */
  public function process(array $payload, array $config): array;
}
