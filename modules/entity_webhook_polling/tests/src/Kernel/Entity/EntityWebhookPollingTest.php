<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_polling\Kernel\Entity;

use Drupal\entity_webhook_polling\Entity\EntityWebhookPolling;
use Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for EntityWebhookPolling config entity CRUD operations.
 *
 * @group entity_webhook_polling
 */
class EntityWebhookPollingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook', 'entity_webhook_polling'];

  /**
   * Tests that an EntityWebhookPolling entity can be created and loaded.
   */
  public function testCreateAndLoadPollingConfig(): void {
    EntityWebhookPolling::create([
      'id' => 'my_polling',
      'label' => 'My Polling',
      'cron_expression' => '*/5 * * * *',
      'polling_provider' => 'example_provider',
      'polling_provider_config' => [],
      'endpoint_id' => 'my_endpoint',
      'source_type_id' => 'my_source',
      'status' => TRUE,
    ])->save();

    $loaded = EntityWebhookPolling::load('my_polling');

    $this->assertInstanceOf(EntityWebhookPollingInterface::class, $loaded);
    $this->assertSame('my_polling', $loaded->id());
    $this->assertSame('My Polling', $loaded->label());
    $this->assertSame('*/5 * * * *', $loaded->getCronExpression());
    $this->assertSame('example_provider', $loaded->getPollingProvider());
    $this->assertSame('my_endpoint', $loaded->getEndpointId());
    $this->assertSame('my_source', $loaded->getSourceTypeId());
    $this->assertTrue($loaded->status());
  }

  /**
   * Tests that polling provider config is stored and retrieved correctly.
   */
  public function testPollingProviderConfigStoredAndRetrieved(): void {
    $config = ['url' => 'https://api.example.com', 'auth_token' => 'secret123'];

    EntityWebhookPolling::create([
      'id' => 'polling_with_config',
      'label' => 'Polling With Config',
      'cron_expression' => '0 * * * *',
      'polling_provider' => 'rest_provider',
      'polling_provider_config' => $config,
      'endpoint_id' => 'endpoint_a',
      'source_type_id' => 'source_a',
      'status' => TRUE,
    ])->save();

    /** @var \Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface $loaded */
    $loaded = EntityWebhookPolling::load('polling_with_config');

    $this->assertSame($config, $loaded->getPollingProviderConfig());
  }

  /**
   * Tests that a disabled EntityWebhookPolling entity reports status correctly.
   */
  public function testDisabledPollingConfigHasFalseStatus(): void {
    EntityWebhookPolling::create([
      'id' => 'disabled_polling',
      'label' => 'Disabled Polling',
      'cron_expression' => '0 0 * * *',
      'polling_provider' => 'example_provider',
      'polling_provider_config' => [],
      'endpoint_id' => 'endpoint_b',
      'source_type_id' => 'source_b',
      'status' => FALSE,
    ])->save();

    /** @var \Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface $loaded */
    $loaded = EntityWebhookPolling::load('disabled_polling');

    $this->assertFalse($loaded->status());
  }

  /**
   * Tests that an EntityWebhookPolling entity can be updated.
   */
  public function testUpdatePollingConfig(): void {
    EntityWebhookPolling::create([
      'id' => 'updatable_polling',
      'label' => 'Original Label',
      'cron_expression' => '*/5 * * * *',
      'polling_provider' => 'provider_a',
      'polling_provider_config' => [],
      'endpoint_id' => 'endpoint_a',
      'source_type_id' => 'source_a',
      'status' => TRUE,
    ])->save();

    /** @var \Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface $loaded */
    $loaded = EntityWebhookPolling::load('updatable_polling');
    $loaded->set('label', 'Updated Label');
    $loaded->set('cron_expression', '0 * * * *');
    $loaded->set('endpoint_id', 'endpoint_b');
    $loaded->save();

    $reloaded = EntityWebhookPolling::load('updatable_polling');
    $this->assertSame('Updated Label', $reloaded->label());
    $this->assertSame('0 * * * *', $reloaded->getCronExpression());
    $this->assertSame('endpoint_b', $reloaded->getEndpointId());
  }

}
