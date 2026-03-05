<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Entity;

use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for WebhookEndpoint config entity CRUD operations.
 *
 * @group entity_webhook
 */
class WebhookEndpointTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook'];

  /**
   * Tests that a WebhookEndpoint can be created and loaded.
   */
  public function testCreateAndLoadWebhookEndpoint(): void {
    WebhookEndpoint::create([
      'id' => 'my_store',
      'label' => 'My Store',
      'target_entity_type' => 'node',
      'source_types' => [],
    ])->save();

    $loaded = WebhookEndpoint::load('my_store');

    $this->assertInstanceOf(WebhookEndpointInterface::class, $loaded);
    $this->assertSame('my_store', $loaded->id());
    $this->assertSame('My Store', $loaded->label());
    $this->assertSame('node', $loaded->getTargetEntityTypeId());
  }

  /**
   * Tests that source type IDs are stored and retrieved correctly.
   */
  public function testSourceTypesStoredAndRetrieved(): void {
    WebhookEndpoint::create([
      'id' => 'endpoint_with_sources',
      'label' => 'Endpoint With Sources',
      'target_entity_type' => 'node',
      'source_types' => ['shopify_order', 'woocommerce_order'],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $loaded */
    $loaded = WebhookEndpoint::load('endpoint_with_sources');
    $sourceTypes = $loaded->getSourceTypeIds();

    $this->assertCount(2, $sourceTypes);
    $this->assertContains('shopify_order', $sourceTypes);
    $this->assertContains('woocommerce_order', $sourceTypes);
  }

  /**
   * Tests hasSourceType returns correct boolean for present and absent IDs.
   */
  public function testHasSourceTypeReturnsTrueForPresentAndFalseForAbsent(): void {
    WebhookEndpoint::create([
      'id' => 'endpoint_check',
      'label' => 'Endpoint Check',
      'target_entity_type' => 'node',
      'source_types' => ['source_a'],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $loaded */
    $loaded = WebhookEndpoint::load('endpoint_check');

    $this->assertTrue($loaded->hasSourceType('source_a'));
    $this->assertFalse($loaded->hasSourceType('source_b'));
  }

  /**
   * Tests that a WebhookEndpoint can be updated.
   */
  public function testUpdateWebhookEndpoint(): void {
    WebhookEndpoint::create([
      'id' => 'updatable_endpoint',
      'label' => 'Original Endpoint',
      'target_entity_type' => 'node',
      'source_types' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $loaded */
    $loaded = WebhookEndpoint::load('updatable_endpoint');
    $loaded->set('label', 'Updated Endpoint');
    $loaded->set('target_entity_type', 'user');
    $loaded->set('source_types', ['new_source']);
    $loaded->save();

    $reloaded = WebhookEndpoint::load('updatable_endpoint');
    $this->assertSame('Updated Endpoint', $reloaded->label());
    $this->assertSame('user', $reloaded->getTargetEntityTypeId());
    $this->assertSame(['new_source'], $reloaded->getSourceTypeIds());
  }

  /**
   * Tests that a WebhookEndpoint can be deleted.
   */
  public function testDeleteWebhookEndpoint(): void {
    WebhookEndpoint::create([
      'id' => 'deletable_endpoint',
      'label' => 'Deletable Endpoint',
      'target_entity_type' => 'node',
      'source_types' => [],
    ])->save();

    $loaded = WebhookEndpoint::load('deletable_endpoint');
    $this->assertNotNull($loaded);

    $loaded->delete();

    $this->assertNull(WebhookEndpoint::load('deletable_endpoint'));
  }

}
