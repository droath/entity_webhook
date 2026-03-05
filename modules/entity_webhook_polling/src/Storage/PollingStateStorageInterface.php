<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Storage;

/**
 * Defines the interface for polling state storage.
 *
 * Polling state tracks the SHA-256 hash of the last-seen field values for each
 * external record per polling configuration. The polling manager compares
 * stored hashes against freshly fetched payloads to detect new and changed
 * records without re-processing unchanged ones.
 */
interface PollingStateStorageInterface {

  /**
   * Saves the hash for a given polling config and external record.
   *
   * If a record for the given polling_id + external_id already exists, the
   * stored hash and last_seen timestamp are updated. Otherwise a new row is
   * inserted.
   *
   * @param string $pollingId
   *   The EntityWebhookPolling config entity ID.
   * @param string $externalId
   *   The external record identifier provided by the polling provider.
   * @param string $hash
   *   The SHA-256 hash of the record's mapped field values.
   */
  public function saveState(string $pollingId, string $externalId, string $hash): void;

  /**
   * Returns the stored hash for a given polling config and external record.
   *
   * @param string $pollingId
   *   The EntityWebhookPolling config entity ID.
   * @param string $externalId
   *   The external record identifier provided by the polling provider.
   *
   * @return string|null
   *   The stored SHA-256 hash, or NULL if no state has been recorded yet.
   */
  public function getHash(string $pollingId, string $externalId): ?string;

}
