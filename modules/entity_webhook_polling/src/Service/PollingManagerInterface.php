<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Service;

/**
 * Orchestrates polling execution across all enabled polling configurations.
 *
 * The polling manager loads enabled EntityWebhookPolling config entities,
 * evaluates their cron schedules, instantiates the appropriate provider plugin,
 * performs hash-based change detection, and queues changed payloads for
 * processing by the core webhook pipeline with source='polling'.
 */
interface PollingManagerInterface {

  /**
   * Runs polling for all enabled configurations whose cron expression is due.
   *
   * Evaluates every enabled EntityWebhookPolling entity. For each entity
   * whose cron expression matches the current time, invokes runPolling().
   */
  public function runAllDue(): void;

  /**
   * Executes polling for a single polling configuration by ID.
   *
   * Fetches all records from the provider, computes their SHA-256 hashes,
   * and queues any records whose hash differs from the stored value.
   *
   * @param string $pollingId
   *   The EntityWebhookPolling config entity ID.
   */
  public function runPolling(string $pollingId): void;

}
