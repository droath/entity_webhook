<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Entity;

use Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for OutboundEndpoint config entity CRUD operations and getters.
 *
 * @group entity_webhook_broadcast
 */
class OutboundEndpointTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook', 'entity_webhook_broadcast'];

  /**
   * Tests that an OutboundEndpoint can be created and loaded with all properties.
   */
  public function testCreateAndLoadOutboundEndpoint(): void {
    // Arrange + Act
    OutboundEndpoint::create([
      'id' => 'node_endpoint',
      'label' => 'Node Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => 'article',
      'events' => ['insert', 'update'],
    ])->save();

    // Assert
    $loaded = OutboundEndpoint::load('node_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $loaded);
    $this->assertSame('node_endpoint', $loaded->id());
    $this->assertSame('Node Endpoint', $loaded->label());
    $this->assertSame('node', $loaded->getWatchedEntityType());
    $this->assertSame('article', $loaded->getEntityBundle());
    $this->assertSame(['insert', 'update'], $loaded->getEvents());
    $this->assertTrue($loaded->isEnabled());
  }

  /**
   * Tests that getEntityBundle returns NULL when no bundle is set.
   */
  public function testGetEntityBundleReturnsNullWhenNotSet(): void {
    // Arrange + Act
    OutboundEndpoint::create([
      'id' => 'all_bundles_endpoint',
      'label' => 'All Bundles Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $loaded */
    $loaded = OutboundEndpoint::load('all_bundles_endpoint');

    $this->assertNull($loaded->getEntityBundle());
  }

  /**
   * Tests that getEvents returns the full events array.
   */
  public function testGetEventsReturnsAllConfiguredEvents(): void {
    // Arrange + Act
    OutboundEndpoint::create([
      'id' => 'all_events_endpoint',
      'label' => 'All Events Endpoint',
      'status' => TRUE,
      'entity_type' => 'user',
      'entity_bundle' => NULL,
      'events' => ['insert', 'update', 'delete'],
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $loaded */
    $loaded = OutboundEndpoint::load('all_events_endpoint');
    $events = $loaded->getEvents();

    $this->assertCount(3, $events);
    $this->assertContains('insert', $events);
    $this->assertContains('update', $events);
    $this->assertContains('delete', $events);
  }

  /**
   * Tests that getEvents returns an empty array when no events are configured.
   */
  public function testGetEventsReturnsEmptyArrayWhenNoneConfigured(): void {
    // Arrange + Act
    OutboundEndpoint::create([
      'id' => 'no_events_endpoint',
      'label' => 'No Events Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => [],
    ])->save();

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $loaded */
    $loaded = OutboundEndpoint::load('no_events_endpoint');

    $this->assertSame([], $loaded->getEvents());
  }

  /**
   * Tests that isEnabled reflects the stored status toggle.
   */
  public function testIsEnabledReflectsStatusToggle(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'disabled_endpoint',
      'label' => 'Disabled Endpoint',
      'status' => FALSE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $loaded */
    $loaded = OutboundEndpoint::load('disabled_endpoint');

    // Assert
    $this->assertFalse($loaded->isEnabled());
  }

  /**
   * Tests that an OutboundEndpoint can be updated and changes are persisted.
   */
  public function testUpdateOutboundEndpoint(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'updatable_endpoint',
      'label' => 'Original Label',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $loaded */
    $loaded = OutboundEndpoint::load('updatable_endpoint');
    $loaded->set('label', 'Updated Label');
    $loaded->set('entity_type', 'user');
    $loaded->set('events', ['update', 'delete']);
    $loaded->set('status', FALSE);
    $loaded->save();

    $reloaded = OutboundEndpoint::load('updatable_endpoint');

    // Assert
    $this->assertSame('Updated Label', $reloaded->label());
    $this->assertSame('user', $reloaded->getWatchedEntityType());
    $this->assertSame(['update', 'delete'], $reloaded->getEvents());
    $this->assertFalse($reloaded->isEnabled());
  }

  /**
   * Tests that an OutboundEndpoint can be deleted.
   */
  public function testDeleteOutboundEndpoint(): void {
    // Arrange
    OutboundEndpoint::create([
      'id' => 'deletable_endpoint',
      'label' => 'Deletable Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    $this->assertNotNull(OutboundEndpoint::load('deletable_endpoint'));

    // Act
    OutboundEndpoint::load('deletable_endpoint')->delete();

    // Assert
    $this->assertNull(OutboundEndpoint::load('deletable_endpoint'));
  }

}
