<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscription;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for OutboundSubscriptionForm: form submission creates config entity.
 *
 * Verifies that submitting the subscription add/edit form:
 * - Creates an OutboundSubscription with all values correctly stored
 * - Associates the entity with its parent OutboundEndpoint from the route
 * - Stores the secret field correctly (including NULL for no-signing)
 * - Defaults the signing algorithm to sha256
 *
 * @group entity_webhook_broadcast
 */
class OutboundSubscriptionFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'entity_webhook_broadcast',
    'user',
    'system',
  ];

  /**
   * The parent OutboundEndpoint entity used in tests.
   */
  private OutboundEndpointInterface $endpoint;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);

    OutboundEndpoint::create([
      'id' => 'test_endpoint',
      'label' => 'Test Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $endpoint */
    $this->endpoint = OutboundEndpoint::load('test_endpoint');
  }

  /**
   * Tests that form submission creates a subscription with correct field values.
   */
  public function testFormSubmissionCreatesSubscriptionWithCorrectValues(): void {
    // Arrange
    $formState = new FormState();
    $formState->setValues([
      'label' => 'My Webhook Subscription',
      'id' => 'my_webhook_subscription',
      'url' => 'https://example.com/webhook',
      'secret' => 'super-secret-key',
      'signing_algorithm' => 'sha256',
      'retry_max_attempts' => 3,
      'retry_base_delay' => 30,
      'active' => TRUE,
      'op' => 'Save',
    ]);

    $subscription = OutboundSubscription::create([]);

    // Act — inject the parent endpoint via a mock route match.
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')
      ->with('outbound_endpoint')
      ->willReturn($this->endpoint);

    $this->container->set('current_route_match', $routeMatch);

    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('outbound_subscription', 'add')
      ->setEntity($subscription);

    $this->container->get('form_builder')->submitForm($form_object, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null $loaded */
    $loaded = OutboundSubscription::load('my_webhook_subscription');

    $this->assertInstanceOf(OutboundSubscriptionInterface::class, $loaded);
    $this->assertSame('My Webhook Subscription', $loaded->label());
    $this->assertSame('https://example.com/webhook', $loaded->getUrl());
    $this->assertSame('super-secret-key', $loaded->getSecret());
    $this->assertSame('sha256', $loaded->getSigningAlgorithm());
    $this->assertSame(3, $loaded->getRetryMaxAttempts());
    $this->assertSame(30, $loaded->getRetryBaseDelay());
    $this->assertTrue($loaded->isActive());
  }

  /**
   * Tests that the form associates the new subscription with the parent endpoint from the route.
   */
  public function testFormSubmissionAssociatesSubscriptionWithParentEndpointFromRoute(): void {
    // Arrange
    $formState = new FormState();
    $formState->setValues([
      'label' => 'Endpoint Child Sub',
      'id' => 'endpoint_child_sub',
      'url' => 'https://example.com/hook',
      'secret' => NULL,
      'signing_algorithm' => 'sha256',
      'retry_max_attempts' => 5,
      'retry_base_delay' => 60,
      'active' => TRUE,
      'op' => 'Save',
    ]);

    $subscription = OutboundSubscription::create([]);

    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')
      ->with('outbound_endpoint')
      ->willReturn($this->endpoint);

    $this->container->set('current_route_match', $routeMatch);

    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('outbound_subscription', 'add')
      ->setEntity($subscription);

    // Act
    $this->container->get('form_builder')->submitForm($form_object, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null $loaded */
    $loaded = OutboundSubscription::load('endpoint_child_sub');

    $this->assertInstanceOf(OutboundSubscriptionInterface::class, $loaded);
    $this->assertSame('test_endpoint', $loaded->getEndpointId());
  }

  /**
   * Tests that submitting with no secret stores NULL (disabling signing).
   */
  public function testFormSubmissionStoresNullSecretWhenSigningIsDisabled(): void {
    // Arrange
    $formState = new FormState();
    $formState->setValues([
      'label' => 'Unsigned Subscription',
      'id' => 'unsigned_subscription',
      'url' => 'https://example.com/unsigned',
      'secret' => '',
      'signing_algorithm' => 'none',
      'retry_max_attempts' => 5,
      'retry_base_delay' => 60,
      'active' => TRUE,
      'op' => 'Save',
    ]);

    $subscription = OutboundSubscription::create([]);

    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')
      ->with('outbound_endpoint')
      ->willReturn($this->endpoint);

    $this->container->set('current_route_match', $routeMatch);

    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('outbound_subscription', 'add')
      ->setEntity($subscription);

    // Act
    $this->container->get('form_builder')->submitForm($form_object, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null $loaded */
    $loaded = OutboundSubscription::load('unsigned_subscription');

    $this->assertInstanceOf(OutboundSubscriptionInterface::class, $loaded);
    $this->assertNull($loaded->getSecret());
    $this->assertSame('none', $loaded->getSigningAlgorithm());
  }

  /**
   * Tests that a new subscription defaults to sha256 signing algorithm.
   */
  public function testDefaultSigningAlgorithmIsSha256(): void {
    // Arrange — create a fresh subscription entity with no overrides.
    $subscription = OutboundSubscription::create([
      'id' => 'default_algo_sub',
      'label' => 'Default Algo Sub',
      'url' => 'https://example.com/hook',
      'active' => TRUE,
    ]);
    $subscription->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $loaded */
    $loaded = OutboundSubscription::load('default_algo_sub');

    // Assert
    $this->assertSame('sha256', $loaded->getSigningAlgorithm());
  }

  /**
   * Tests that editing a subscription persists changed URL and algorithm values.
   */
  public function testEditFormSubmissionUpdatesSubscriptionUrlAndAlgorithm(): void {
    // Arrange
    OutboundSubscription::create([
      'id' => 'editable_sub',
      'label' => 'Editable Subscription',
      'endpoint_id' => 'test_endpoint',
      'url' => 'https://original.example.com/webhook',
      'secret' => 'orig-secret',
      'signing_algorithm' => 'sha256',
      'retry_max_attempts' => 5,
      'retry_base_delay' => 60,
      'active' => TRUE,
    ])->save();

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $existing */
    $existing = OutboundSubscription::load('editable_sub');

    $formState = new FormState();
    $formState->setValues([
      'label' => 'Editable Subscription',
      'id' => 'editable_sub',
      'url' => 'https://updated.example.com/webhook',
      'secret' => 'new-secret',
      'signing_algorithm' => 'sha1',
      'retry_max_attempts' => 10,
      'retry_base_delay' => 120,
      'active' => 0,
      'op' => 'Save',
    ]);

    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')
      ->with('outbound_endpoint')
      ->willReturn($this->endpoint);

    $this->container->set('current_route_match', $routeMatch);

    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('outbound_subscription', 'edit')
      ->setEntity($existing);

    // Act
    $this->container->get('form_builder')->submitForm($form_object, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null $reloaded */
    $reloaded = OutboundSubscription::load('editable_sub');

    $this->assertInstanceOf(OutboundSubscriptionInterface::class, $reloaded);
    $this->assertSame('https://updated.example.com/webhook', $reloaded->getUrl());
    $this->assertSame('new-secret', $reloaded->getSecret());
    $this->assertSame('sha1', $reloaded->getSigningAlgorithm());
    $this->assertSame(10, $reloaded->getRetryMaxAttempts());
    $this->assertSame(120, $reloaded->getRetryBaseDelay());
  }

}
