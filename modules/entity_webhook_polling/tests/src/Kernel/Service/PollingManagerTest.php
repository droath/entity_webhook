<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_polling\Kernel\Service;

use Drupal\entity_webhook\Queue\WebhookQueueServiceInterface;
use Drupal\entity_webhook_polling\Entity\EntityWebhookPolling;
use Drupal\entity_webhook_polling\Service\PollingManagerInterface;
use Drupal\entity_webhook_polling_test\Plugin\PollingProvider\TestPollingProvider;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the PollingManager service.
 *
 * @group entity_webhook_polling
 */
class PollingManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'entity_webhook_polling',
    'entity_webhook_polling_test',
  ];

  /**
   * The polling manager service.
   */
  private PollingManagerInterface $pollingManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('entity_webhook_polling', ['entity_webhook_polling_state']);
    $this->pollingManager = $this->container->get('entity_webhook_polling.polling_manager');
  }

  /**
   * Tests that a new payload is queued and its hash stored on first fetch.
   */
  public function testNewPayloadIsQueuedOnFirstFetch(): void {
    TestPollingProvider::setPayloads([
      ['id' => '1', 'name' => 'Record A'],
    ]);

    EntityWebhookPolling::create([
      'id' => 'test_polling',
      'label' => 'Test Polling',
      'status' => TRUE,
      'cron_expression' => '* * * * *',
      'polling_provider' => 'test_polling_provider',
      'polling_provider_config' => [],
      'endpoint_id' => 'my_endpoint',
      'source_type_id' => 'my_source',
    ])->save();

    $queueService = $this->container->get('entity_webhook.webhook_queue');
    $depthBefore = $queueService->getQueueDepth();

    $this->pollingManager->runPolling('test_polling');

    $this->assertGreaterThan($depthBefore, $queueService->getQueueDepth());
  }

  /**
   * Tests that an unchanged payload is not re-queued on subsequent fetch.
   */
  public function testUnchangedPayloadIsNotRequeuedOnSubsequentFetch(): void {
    TestPollingProvider::setPayloads([
      ['id' => '1', 'name' => 'Record A'],
    ]);

    EntityWebhookPolling::create([
      'id' => 'test_polling_no_change',
      'label' => 'Test Polling No Change',
      'status' => TRUE,
      'cron_expression' => '* * * * *',
      'polling_provider' => 'test_polling_provider',
      'polling_provider_config' => [],
      'endpoint_id' => 'my_endpoint',
      'source_type_id' => 'my_source',
    ])->save();

    $queueService = $this->container->get('entity_webhook.webhook_queue');

    // First run — payload is new, so it should be queued.
    $this->pollingManager->runPolling('test_polling_no_change');
    $depthAfterFirst = $queueService->getQueueDepth();

    // Second run — same payload, hash unchanged, nothing new should be queued.
    $this->pollingManager->runPolling('test_polling_no_change');
    $depthAfterSecond = $queueService->getQueueDepth();

    $this->assertSame($depthAfterFirst, $depthAfterSecond);
  }

  /**
   * Tests that a changed payload is re-queued after modification.
   */
  public function testChangedPayloadIsRequeuedAfterModification(): void {
    EntityWebhookPolling::create([
      'id' => 'test_polling_change',
      'label' => 'Test Polling Change',
      'status' => TRUE,
      'cron_expression' => '* * * * *',
      'polling_provider' => 'test_polling_provider',
      'polling_provider_config' => [],
      'endpoint_id' => 'my_endpoint',
      'source_type_id' => 'my_source',
    ])->save();

    $queueService = $this->container->get('entity_webhook.webhook_queue');

    TestPollingProvider::setPayloads([['id' => '1', 'name' => 'Original']]);
    $this->pollingManager->runPolling('test_polling_change');
    $depthAfterFirst = $queueService->getQueueDepth();

    // Change the payload content — hash should differ, triggering re-queue.
    TestPollingProvider::setPayloads([['id' => '1', 'name' => 'Modified']]);
    $this->pollingManager->runPolling('test_polling_change');
    $depthAfterSecond = $queueService->getQueueDepth();

    $this->assertGreaterThan($depthAfterFirst, $depthAfterSecond);
  }

  /**
   * Tests that a disabled polling config is not executed.
   */
  public function testDisabledPollingConfigIsSkipped(): void {
    TestPollingProvider::setPayloads([
      ['id' => '1', 'name' => 'Record A'],
    ]);

    EntityWebhookPolling::create([
      'id' => 'disabled_polling_test',
      'label' => 'Disabled Polling',
      'status' => FALSE,
      'cron_expression' => '* * * * *',
      'polling_provider' => 'test_polling_provider',
      'polling_provider_config' => [],
      'endpoint_id' => 'my_endpoint',
      'source_type_id' => 'my_source',
    ])->save();

    $queueService = $this->container->get('entity_webhook.webhook_queue');
    $depthBefore = $queueService->getQueueDepth();

    $this->pollingManager->runAllDue();

    $this->assertSame($depthBefore, $queueService->getQueueDepth());
  }

}
