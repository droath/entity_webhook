<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for EntityWebhookPolling config entities.
 *
 * An EntityWebhookPolling entity represents a polling configuration that
 * fetches external data on a cron schedule and routes it through the core
 * entity webhook processing pipeline.
 */
interface EntityWebhookPollingInterface extends ConfigEntityInterface {

  /**
   * Returns the cron expression defining the polling schedule.
   *
   * @return string
   *   A cron expression string (e.g., "* /5 * * * *").
   */
  public function getCronExpression(): string;

  /**
   * Returns the polling provider plugin ID.
   *
   * @return string
   *   The plugin ID of the polling provider.
   */
  public function getPollingProvider(): string;

  /**
   * Returns the polling provider plugin configuration.
   *
   * @return array<string, mixed>
   *   The plugin configuration array.
   */
  public function getPollingProviderConfig(): array;

  /**
   * Returns the WebhookEndpoint ID this polling config routes payloads to.
   *
   * @return string
   *   The WebhookEndpoint machine name.
   */
  public function getEndpointId(): string;

  /**
   * Returns the WebhookSourceType ID used for payload processing.
   *
   * @return string
   *   The WebhookSourceType machine name.
   */
  public function getSourceTypeId(): string;

}
