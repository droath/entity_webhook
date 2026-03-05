<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Storage;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Stores and retrieves polling state using the entity_webhook_polling_state
 * database table.
 *
 * Uses INSERT ... ON DUPLICATE KEY UPDATE (MySQL/MariaDB) via Drupal's
 * upsert query builder so that a single call handles both new records and
 * updates to existing ones.
 */
class PollingStateStorage implements PollingStateStorageInterface {

  /**
   * The database table used for polling state.
   */
  private const TABLE = 'entity_webhook_polling_state';

  /**
   * Constructs a new PollingStateStorage.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function saveState(string $pollingId, string $externalId, string $hash): void {
    $this->database->merge(self::TABLE)
      ->keys([
        'polling_id' => $pollingId,
        'external_id' => $externalId,
      ])
      ->fields([
        'hash' => $hash,
        'last_seen' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getHash(string $pollingId, string $externalId): ?string {
    $result = $this->database->select(self::TABLE, 's')
      ->fields('s', ['hash'])
      ->condition('s.polling_id', $pollingId)
      ->condition('s.external_id', $externalId)
      ->execute()
      ->fetchField();

    return $result !== FALSE ? (string) $result : NULL;
  }

}
