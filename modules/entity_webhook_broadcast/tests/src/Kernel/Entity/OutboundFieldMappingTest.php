<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Entity;

use Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint;
use Drupal\entity_webhook_broadcast\Entity\OutboundFieldMapping;
use Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscription;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for OutboundFieldMapping config entity CRUD, composite ID, and cascade deletion.
 *
 * @group entity_webhook_broadcast
 */
class OutboundFieldMappingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook', 'entity_webhook_broadcast'];

  /**
   * Tests that an OutboundFieldMapping can be created and loaded with all properties.
   */
  public function testCreateAndLoadOutboundFieldMapping(): void {
    // Arrange + Act
    OutboundFieldMapping::create([
      'id' => 'my_sub.title',
      'label' => 'Title Mapping',
      'subscription_id' => 'my_sub',
      'entity_field' => 'title',
      'output_key' => 'title',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Assert
    $loaded = OutboundFieldMapping::load('my_sub.title');

    $this->assertInstanceOf(OutboundFieldMappingInterface::class, $loaded);
    $this->assertSame('my_sub.title', $loaded->id());
    $this->assertSame('Title Mapping', $loaded->label());
    $this->assertSame('my_sub', $loaded->getSubscriptionId());
    $this->assertSame('title', $loaded->getEntityField());
    $this->assertSame('title', $loaded->getOutputKey());
    $this->assertSame('', $loaded->getMutationPlugin());
    $this->assertSame([], $loaded->getMutationConfig());
  }

  /**
   * Tests that the composite ID format uses subscription_id.output_key.
   */
  public function testCompositeIdFormatMatchesSubscriptionAndOutputKey(): void {
    // Arrange + Act
    OutboundFieldMapping::create([
      'id' => 'acme_sub.node_title',
      'label' => 'Node Title',
      'subscription_id' => 'acme_sub',
      'entity_field' => 'title',
      'output_key' => 'node_title',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Assert
    $loaded = OutboundFieldMapping::load('acme_sub.node_title');

    $this->assertNotNull($loaded);
    $this->assertSame('acme_sub.node_title', $loaded->id());
    $this->assertSame('acme_sub', $loaded->getSubscriptionId());
    $this->assertSame('node_title', $loaded->getOutputKey());
  }

  /**
   * Tests that mutation_plugin and mutation_config are stored and retrieved correctly.
   */
  public function testMutationPluginAndConfigStoredAndRetrieved(): void {
    // Arrange + Act
    OutboundFieldMapping::create([
      'id' => 'sub_with_mutation.price',
      'label' => 'Price Mapping',
      'subscription_id' => 'sub_with_mutation',
      'entity_field' => 'field_price',
      'output_key' => 'price',
      'mutation_plugin' => 'price_cents_to_decimal',
      'mutation_config' => ['precision' => 2, 'currency' => 'USD'],
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $loaded */
    $loaded = OutboundFieldMapping::load('sub_with_mutation.price');

    $this->assertSame('price_cents_to_decimal', $loaded->getMutationPlugin());
    $this->assertSame(['precision' => 2, 'currency' => 'USD'], $loaded->getMutationConfig());
  }

  /**
   * Tests that getSubscription returns the parent entity when it exists.
   */
  public function testGetSubscriptionReturnsParentEntity(): void {
    // Arrange
    OutboundSubscription::create([
      'id' => 'real_sub',
      'label' => 'Real Subscription',
      'endpoint_id' => 'some_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    OutboundFieldMapping::create([
      'id' => 'real_sub.body',
      'label' => 'Body Mapping',
      'subscription_id' => 'real_sub',
      'entity_field' => 'body',
      'output_key' => 'body',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $loaded */
    $loaded = OutboundFieldMapping::load('real_sub.body');
    $subscription = $loaded->getSubscription();

    // Assert
    $this->assertNotNull($subscription);
    $this->assertSame('real_sub', $subscription->id());
  }

  /**
   * Tests that getSubscription returns NULL when the parent subscription does not exist.
   */
  public function testGetSubscriptionReturnsNullWhenParentMissing(): void {
    // Arrange + Act
    OutboundFieldMapping::create([
      'id' => 'ghost_sub.field_x',
      'label' => 'Field X',
      'subscription_id' => 'ghost_sub',
      'entity_field' => 'field_x',
      'output_key' => 'field_x',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $loaded */
    $loaded = OutboundFieldMapping::load('ghost_sub.field_x');

    $this->assertNull($loaded->getSubscription());
  }

  /**
   * Tests that calculateDependencies declares a config dependency on the parent subscription.
   */
  public function testCalculateDependenciesIncludesParentSubscription(): void {
    // Arrange
    OutboundSubscription::create([
      'id' => 'dep_sub',
      'label' => 'Dependency Subscription',
      'endpoint_id' => 'some_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    OutboundFieldMapping::create([
      'id' => 'dep_sub.status',
      'label' => 'Status Mapping',
      'subscription_id' => 'dep_sub',
      'entity_field' => 'status',
      'output_key' => 'status',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $loaded */
    $loaded = OutboundFieldMapping::load('dep_sub.status');
    $dependencies = $loaded->getDependencies();

    // Assert
    $this->assertArrayHasKey('config', $dependencies);
    $this->assertContains(
      'entity_webhook_broadcast.outbound_subscription.dep_sub',
      $dependencies['config'],
    );
  }

  /**
   * Tests that deleting a parent OutboundSubscription cascade-deletes its field mappings.
   */
  public function testCascadeDeletionWhenSubscriptionIsDeleted(): void {
    // Arrange
    OutboundSubscription::create([
      'id' => 'cascade_sub',
      'label' => 'Cascade Subscription',
      'endpoint_id' => 'some_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    OutboundFieldMapping::create([
      'id' => 'cascade_sub.field_one',
      'label' => 'Field One',
      'subscription_id' => 'cascade_sub',
      'entity_field' => 'field_one',
      'output_key' => 'field_one',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    OutboundFieldMapping::create([
      'id' => 'cascade_sub.field_two',
      'label' => 'Field Two',
      'subscription_id' => 'cascade_sub',
      'entity_field' => 'field_two',
      'output_key' => 'field_two',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    $this->assertNotNull(OutboundFieldMapping::load('cascade_sub.field_one'));
    $this->assertNotNull(OutboundFieldMapping::load('cascade_sub.field_two'));

    // Act
    OutboundSubscription::load('cascade_sub')->delete();

    // Assert
    $this->assertNull(OutboundFieldMapping::load('cascade_sub.field_one'));
    $this->assertNull(OutboundFieldMapping::load('cascade_sub.field_two'));
  }

  /**
   * Tests that deleting an OutboundEndpoint cascade-deletes subscriptions and their field mappings.
   */
  public function testCascadeDeletionFromEndpointRemovesFieldMappings(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'top_ep',
      'label' => 'Top Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'mid_sub',
      'label' => 'Mid Subscription',
      'endpoint_id' => 'top_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    OutboundFieldMapping::create([
      'id' => 'mid_sub.leaf_field',
      'label' => 'Leaf Field',
      'subscription_id' => 'mid_sub',
      'entity_field' => 'leaf_field',
      'output_key' => 'leaf_field',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    $this->assertNotNull(OutboundFieldMapping::load('mid_sub.leaf_field'));
    $this->assertNotNull(OutboundSubscription::load('mid_sub'));

    // Act
    OutboundEndpoint::load('top_ep')->delete();

    // Assert
    $this->assertNull(OutboundSubscription::load('mid_sub'));
    $this->assertNull(OutboundFieldMapping::load('mid_sub.leaf_field'));
  }

  /**
   * Tests that an OutboundFieldMapping can be updated and changes are persisted.
   */
  public function testUpdateOutboundFieldMapping(): void {
    // Arrange
    OutboundFieldMapping::create([
      'id' => 'updatable_sub.field_a',
      'label' => 'Original Label',
      'subscription_id' => 'updatable_sub',
      'entity_field' => 'field_a',
      'output_key' => 'field_a',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $loaded */
    $loaded = OutboundFieldMapping::load('updatable_sub.field_a');
    $loaded->set('label', 'Updated Label');
    $loaded->set('mutation_plugin', 'some_plugin');
    $loaded->set('mutation_config', ['key' => 'value']);
    $loaded->save();

    $reloaded = OutboundFieldMapping::load('updatable_sub.field_a');

    // Assert
    $this->assertSame('Updated Label', $reloaded->label());
    $this->assertSame('some_plugin', $reloaded->getMutationPlugin());
    $this->assertSame(['key' => 'value'], $reloaded->getMutationConfig());
  }

  /**
   * Tests that an OutboundFieldMapping can be deleted.
   */
  public function testDeleteOutboundFieldMapping(): void {
    // Arrange
    OutboundFieldMapping::create([
      'id' => 'del_sub.field_z',
      'label' => 'Field Z',
      'subscription_id' => 'del_sub',
      'entity_field' => 'field_z',
      'output_key' => 'field_z',
      'mutation_plugin' => '',
      'mutation_config' => [],
    ])->save();

    $this->assertNotNull(OutboundFieldMapping::load('del_sub.field_z'));

    // Act
    OutboundFieldMapping::load('del_sub.field_z')->delete();

    // Assert
    $this->assertNull(OutboundFieldMapping::load('del_sub.field_z'));
  }

}
