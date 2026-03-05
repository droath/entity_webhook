<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_polling\Kernel\Service;

use Drupal\entity_webhook_polling\Entity\EntityWebhookPolling;
use Drupal\entity_webhook_polling_test\Plugin\PollingProvider\TestPollingProvider;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for cron hook integration with the polling manager.
 *
 * @group entity_webhook_polling
 */
class CronIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'entity_webhook_polling',
    'entity_webhook_polling_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('entity_webhook_polling', ['entity_webhook_polling_state']);
  }

  /**
   * Tests that cron invocation queues payloads from due polling configs.
   */
  public function testCronQueuesPayloadsFromDuePollingConfigs(): void {
    TestPollingProvider::setPayloads([
      ['id' => 'cron_rec_1', 'title' => 'Cron Record 1'],
    ]);

    EntityWebhookPolling::create([
      'id' => 'cron_test_polling',
      'label' => 'Cron Test Polling',
      'status' => TRUE,
      'cron_expression' => '* * * * *',
      'polling_provider' => 'test_polling_provider',
      'polling_provider_config' => [],
      'endpoint_id' => 'ep_cron',
      'source_type_id' => 'st_cron',
    ])->save();

    $queueService = $this->container->get('entity_webhook.webhook_queue');
    $depthBefore = $queueService->getQueueDepth();

    // Invoke cron through the module handler to test hook_cron integration.
    $this->container->get('module_handler')->invoke('entity_webhook_polling', 'cron');

    $this->assertGreaterThan($depthBefore, $queueService->getQueueDepth());
  }

  /**
   * Tests that a concurrent lock prevents double execution during cron.
   *
   * Replaces the 'lock' service with a mock that returns FALSE to simulate
   * another process holding the lock. With the lock unavailable, cron must
   * be a no-op and leave the queue unchanged.
   */
  public function testStateLockPreventsConcurrentPollingExecution(): void {
    TestPollingProvider::setPayloads([
      ['id' => 'lock_rec_1', 'title' => 'Lock Record'],
    ]);

    EntityWebhookPolling::create([
      'id' => 'lock_test_polling',
      'label' => 'Lock Test Polling',
      'status' => TRUE,
      'cron_expression' => '* * * * *',
      'polling_provider' => 'test_polling_provider',
      'polling_provider_config' => [],
      'endpoint_id' => 'ep_lock',
      'source_type_id' => 'st_lock',
    ])->save();

    $queueService = $this->container->get('entity_webhook.webhook_queue');

    // Replace the lock service with one that refuses all acquisitions.
    $lockMock = $this->createMock(\Drupal\Core\Lock\LockBackendInterface::class);
    $lockMock->method('acquire')->willReturn(FALSE);
    $this->container->set('lock', $lockMock);

    $depthBefore = $queueService->getQueueDepth();

    // Invoking cron while the lock is unavailable should be a no-op.
    $this->container->get('module_handler')->invoke('entity_webhook_polling', 'cron');

    $this->assertSame($depthBefore, $queueService->getQueueDepth());
  }

}
