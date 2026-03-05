<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_polling\Kernel\Service;

use Drupal\entity_webhook_polling\Storage\PollingStateStorageInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for PollingStateStorage service.
 *
 * @group entity_webhook_polling
 */
class PollingStateStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook', 'entity_webhook_polling'];

  /**
   * The polling state storage service.
   */
  private PollingStateStorageInterface $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('entity_webhook_polling', ['entity_webhook_polling_state']);
    $this->storage = $this->container->get('entity_webhook_polling.state_storage');
  }

  /**
   * Tests that a hash can be stored and retrieved for a given polling config and
   * external ID.
   */
  public function testStoreAndRetrieveHash(): void {
    $pollingId = 'my_polling_config';
    $externalId = 'record_123';
    $hash = hash('sha256', 'some_data');

    $this->storage->saveState($pollingId, $externalId, $hash);

    $retrieved = $this->storage->getHash($pollingId, $externalId);

    $this->assertSame($hash, $retrieved);
  }

  /**
   * Tests that retrieving a hash for an unknown record returns null.
   */
  public function testGetHashReturnsNullForUnknownRecord(): void {
    $result = $this->storage->getHash('nonexistent_polling', 'nonexistent_id');

    $this->assertNull($result);
  }

  /**
   * Tests that saving state for an existing record updates the hash.
   */
  public function testSaveStateUpdatesExistingRecord(): void {
    $pollingId = 'my_polling_config';
    $externalId = 'record_456';
    $originalHash = hash('sha256', 'original_data');
    $updatedHash = hash('sha256', 'updated_data');

    $this->storage->saveState($pollingId, $externalId, $originalHash);
    $this->storage->saveState($pollingId, $externalId, $updatedHash);

    $retrieved = $this->storage->getHash($pollingId, $externalId);

    $this->assertSame($updatedHash, $retrieved);
  }

  /**
   * Tests that different polling configs track state independently.
   */
  public function testDifferentPollingConfigsAreIndependent(): void {
    $externalId = 'shared_record_id';
    $hashA = hash('sha256', 'config_a_data');
    $hashB = hash('sha256', 'config_b_data');

    $this->storage->saveState('config_a', $externalId, $hashA);
    $this->storage->saveState('config_b', $externalId, $hashB);

    $this->assertSame($hashA, $this->storage->getHash('config_a', $externalId));
    $this->assertSame($hashB, $this->storage->getHash('config_b', $externalId));
  }

}
