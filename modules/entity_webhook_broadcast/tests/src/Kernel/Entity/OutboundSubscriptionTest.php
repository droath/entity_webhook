<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Entity;

use Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscription;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for OutboundSubscription config entity CRUD, getters, and cascade deletion.
 *
 * @group entity_webhook_broadcast
 */
class OutboundSubscriptionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook', 'entity_webhook_broadcast'];

  /**
   * Tests that an OutboundSubscription can be created and loaded with all properties.
   */
  public function testCreateAndLoadOutboundSubscription(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'node_ep',
      'label' => 'Node Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    // Act
    OutboundSubscription::create([
      'id' => 'my_subscription',
      'label' => 'My Subscription',
      'endpoint_id' => 'node_ep',
      'url' => 'https://example.com/webhook',
      'secret' => 'my-secret-key',
      'signing_algorithm' => 'sha256',
      'retry_max_attempts' => 3,
      'retry_base_delay' => 30,
      'active' => TRUE,
    ])->save();

    // Assert
    $loaded = OutboundSubscription::load('my_subscription');

    $this->assertInstanceOf(OutboundSubscriptionInterface::class, $loaded);
    $this->assertSame('my_subscription', $loaded->id());
    $this->assertSame('My Subscription', $loaded->label());
    $this->assertSame('node_ep', $loaded->getEndpointId());
    $this->assertSame('https://example.com/webhook', $loaded->getUrl());
    $this->assertSame('my-secret-key', $loaded->getSecret());
    $this->assertSame('sha256', $loaded->getSigningAlgorithm());
    $this->assertSame(3, $loaded->getRetryMaxAttempts());
    $this->assertSame(30, $loaded->getRetryBaseDelay());
    $this->assertTrue($loaded->isActive());
  }

  /**
   * Tests that default values for retry settings are applied correctly.
   */
  public function testDefaultRetrySettingsApplied(): void {
    // Arrange + Act
    OutboundSubscription::create([
      'id' => 'defaults_subscription',
      'label' => 'Defaults Subscription',
      'endpoint_id' => 'some_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $loaded */
    $loaded = OutboundSubscription::load('defaults_subscription');

    $this->assertSame(5, $loaded->getRetryMaxAttempts());
    $this->assertSame(60, $loaded->getRetryBaseDelay());
    $this->assertSame('sha256', $loaded->getSigningAlgorithm());
  }

  /**
   * Tests that getSecret returns NULL when no secret is stored.
   */
  public function testGetSecretReturnsNullWhenNotSet(): void {
    // Arrange + Act
    OutboundSubscription::create([
      'id' => 'no_secret_subscription',
      'label' => 'No Secret Subscription',
      'endpoint_id' => 'some_ep',
      'url' => 'https://example.com/webhook',
      'secret' => NULL,
      'active' => TRUE,
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $loaded */
    $loaded = OutboundSubscription::load('no_secret_subscription');

    $this->assertNull($loaded->getSecret());
  }

  /**
   * Tests that isActive returns FALSE when subscription is paused.
   */
  public function testIsActiveReturnsFalseWhenPaused(): void {
    // Arrange + Act
    OutboundSubscription::create([
      'id' => 'paused_subscription',
      'label' => 'Paused Subscription',
      'endpoint_id' => 'some_ep',
      'url' => 'https://example.com/webhook',
      'active' => FALSE,
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $loaded */
    $loaded = OutboundSubscription::load('paused_subscription');

    $this->assertFalse($loaded->isActive());
  }

  /**
   * Tests that getEndpoint returns the parent OutboundEndpoint entity.
   */
  public function testGetEndpointReturnsParentEntity(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'parent_ep',
      'label' => 'Parent Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'child_subscription',
      'label' => 'Child Subscription',
      'endpoint_id' => 'parent_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $loaded */
    $loaded = OutboundSubscription::load('child_subscription');
    $endpoint = $loaded->getEndpoint();

    // Assert
    $this->assertNotNull($endpoint);
    $this->assertSame('parent_ep', $endpoint->id());
  }

  /**
   * Tests that getEndpoint returns NULL when the parent endpoint does not exist.
   */
  public function testGetEndpointReturnsNullWhenParentMissing(): void {
    // Arrange + Act
    OutboundSubscription::create([
      'id' => 'orphan_subscription',
      'label' => 'Orphan Subscription',
      'endpoint_id' => 'nonexistent_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $loaded */
    $loaded = OutboundSubscription::load('orphan_subscription');
    $endpoint = $loaded->getEndpoint();

    $this->assertNull($endpoint);
  }

  /**
   * Tests that calculateDependencies declares a config dependency on the parent endpoint.
   */
  public function testCalculateDependenciesIncludesParentEndpoint(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'dep_ep',
      'label' => 'Dependency Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'dependent_subscription',
      'label' => 'Dependent Subscription',
      'endpoint_id' => 'dep_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $loaded */
    $loaded = OutboundSubscription::load('dependent_subscription');
    $dependencies = $loaded->getDependencies();

    // Assert
    $this->assertArrayHasKey('config', $dependencies);
    $this->assertContains(
      'entity_webhook_broadcast.outbound_endpoint.dep_ep',
      $dependencies['config'],
    );
  }

  /**
   * Tests that deleting a parent OutboundEndpoint cascade-deletes its subscriptions.
   */
  public function testCascadeDeletionWhenEndpointIsDeleted(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'cascade_ep',
      'label' => 'Cascade Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    OutboundSubscription::create([
      'id' => 'cascade_sub_one',
      'label' => 'Cascade Sub One',
      'endpoint_id' => 'cascade_ep',
      'url' => 'https://example.com/webhook/one',
      'active' => TRUE,
    ])->save();

    OutboundSubscription::create([
      'id' => 'cascade_sub_two',
      'label' => 'Cascade Sub Two',
      'endpoint_id' => 'cascade_ep',
      'url' => 'https://example.com/webhook/two',
      'active' => TRUE,
    ])->save();

    $this->assertNotNull(OutboundSubscription::load('cascade_sub_one'));
    $this->assertNotNull(OutboundSubscription::load('cascade_sub_two'));

    // Act
    OutboundEndpoint::load('cascade_ep')->delete();

    // Assert
    $this->assertNull(OutboundSubscription::load('cascade_sub_one'));
    $this->assertNull(OutboundSubscription::load('cascade_sub_two'));
  }

  /**
   * Tests that an OutboundSubscription can be updated and changes are persisted.
   */
  public function testUpdateOutboundSubscription(): void {
    // Arrange
    OutboundSubscription::create([
      'id' => 'updatable_subscription',
      'label' => 'Original Label',
      'endpoint_id' => 'some_ep',
      'url' => 'https://example.com/original',
      'signing_algorithm' => 'sha256',
      'active' => TRUE,
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $loaded */
    $loaded = OutboundSubscription::load('updatable_subscription');
    $loaded->set('label', 'Updated Label');
    $loaded->set('url', 'https://example.com/updated');
    $loaded->set('signing_algorithm', 'sha1');
    $loaded->set('active', FALSE);
    $loaded->save();

    $reloaded = OutboundSubscription::load('updatable_subscription');

    // Assert
    $this->assertSame('Updated Label', $reloaded->label());
    $this->assertSame('https://example.com/updated', $reloaded->getUrl());
    $this->assertSame('sha1', $reloaded->getSigningAlgorithm());
    $this->assertFalse($reloaded->isActive());
  }

  /**
   * Tests that an OutboundSubscription can be deleted.
   */
  public function testDeleteOutboundSubscription(): void {
    // Arrange
    OutboundSubscription::create([
      'id' => 'deletable_subscription',
      'label' => 'Deletable Subscription',
      'endpoint_id' => 'some_ep',
      'url' => 'https://example.com/webhook',
      'active' => TRUE,
    ])->save();

    $this->assertNotNull(OutboundSubscription::load('deletable_subscription'));

    // Act
    OutboundSubscription::load('deletable_subscription')->delete();

    // Assert
    $this->assertNull(OutboundSubscription::load('deletable_subscription'));
  }

}
