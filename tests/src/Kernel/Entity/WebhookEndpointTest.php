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

  /**
   * Tests that target_entity_bundle is stored and retrieved correctly.
   */
  public function testTargetEntityBundleStoredAndRetrieved(): void {
    WebhookEndpoint::create([
      'id' => 'bundle_endpoint',
      'label' => 'Bundle Endpoint',
      'target_entity_type' => 'node',
      'target_entity_bundle' => 'article',
      'source_types' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $loaded */
    $loaded = WebhookEndpoint::load('bundle_endpoint');

    $this->assertSame('article', $loaded->getTargetEntityBundle());
  }

  /**
   * Tests that getTargetEntityBundle returns null when not set.
   */
  public function testTargetEntityBundleDefaultsToNull(): void {
    WebhookEndpoint::create([
      'id' => 'no_bundle_endpoint',
      'label' => 'No Bundle Endpoint',
      'target_entity_type' => 'node',
      'source_types' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $loaded */
    $loaded = WebhookEndpoint::load('no_bundle_endpoint');

    $this->assertNull($loaded->getTargetEntityBundle());
  }

  /**
   * Tests addSourceType appends a new source type ID.
   */
  public function testAddSourceTypeAppendsNewId(): void {
    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint */
    $endpoint = WebhookEndpoint::create([
      'id' => 'add_source_endpoint',
      'label' => 'Add Source Endpoint',
      'target_entity_type' => 'node',
      'source_types' => ['source_a'],
    ]);

    $endpoint->addSourceType('source_b');

    $this->assertContains('source_a', $endpoint->getSourceTypeIds());
    $this->assertContains('source_b', $endpoint->getSourceTypeIds());
    $this->assertCount(2, $endpoint->getSourceTypeIds());
  }

  /**
   * Tests addSourceType does not add a duplicate ID.
   */
  public function testAddSourceTypeIgnoresDuplicate(): void {
    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint */
    $endpoint = WebhookEndpoint::create([
      'id' => 'duplicate_source_endpoint',
      'label' => 'Duplicate Source Endpoint',
      'target_entity_type' => 'node',
      'source_types' => ['source_a'],
    ]);

    $endpoint->addSourceType('source_a');

    $this->assertCount(1, $endpoint->getSourceTypeIds());
  }

  /**
   * Tests removeSourceType removes the given ID and reindexes the array.
   */
  public function testRemoveSourceTypeRemovesIdAndReindexes(): void {
    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint */
    $endpoint = WebhookEndpoint::create([
      'id' => 'remove_source_endpoint',
      'label' => 'Remove Source Endpoint',
      'target_entity_type' => 'node',
      'source_types' => ['source_a', 'source_b', 'source_c'],
    ]);

    $endpoint->removeSourceType('source_b');

    $ids = $endpoint->getSourceTypeIds();
    $this->assertCount(2, $ids);
    $this->assertNotContains('source_b', $ids);
    $this->assertSame(['source_a', 'source_c'], array_values($ids));
  }

  /**
   * Tests removeSourceType is a no-op when the ID is absent.
   */
  public function testRemoveSourceTypeIsNoOpForAbsentId(): void {
    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint */
    $endpoint = WebhookEndpoint::create([
      'id' => 'noop_remove_endpoint',
      'label' => 'Noop Remove Endpoint',
      'target_entity_type' => 'node',
      'source_types' => ['source_a'],
    ]);

    $endpoint->removeSourceType('nonexistent');

    $this->assertCount(1, $endpoint->getSourceTypeIds());
    $this->assertContains('source_a', $endpoint->getSourceTypeIds());
  }

}
