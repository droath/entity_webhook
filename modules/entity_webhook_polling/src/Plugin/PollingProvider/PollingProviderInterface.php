<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Plugin\PollingProvider;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for polling provider plugins.
 *
 * Polling providers fetch records from an external source during cron runs.
 * Each fetched record is returned as an array payload that is routed through
 * the core entity webhook queue pipeline with source='polling'.
 *
 * Implementations must be annotated or attributed with the PollingProvider
 * plugin attribute.
 *
 * @see \Drupal\entity_webhook_polling\Attribute\PollingProvider
 * @see \Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderBase
 */
interface PollingProviderInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {

  /**
   * Fetches records from the external source.
   *
   * Each returned item is an associative array of field-value pairs that
   * mirrors the structure expected by the webhook payload field mappings on
   * the associated WebhookSourceType. The polling manager uses these payloads
   * for hash-based change detection and queuing.
   *
   * @return array<int, array<string, mixed>>
   *   An array of payload arrays, one per fetched record.
   */
  public function fetch(): array;

  /**
   * Returns the human-readable label for this polling provider plugin.
   *
   * @return string
   *   The plugin label.
   */
  public function label(): string;

}
