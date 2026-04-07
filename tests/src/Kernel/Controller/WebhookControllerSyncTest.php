<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Controller;

use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for WebhookController sync vs async branching.
 *
 * @group entity_webhook
 */
class WebhookControllerSyncTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
  }

  /**
   * Tests that an async endpoint enqueues the payload and returns 'queued'.
   */
  public function testAsyncEndpointEnqueuesPayloadAndReturnsQueued(): void {
    // Arrange
    WebhookEndpoint::create([
      'id' => 'async_endpoint',
      'label' => 'Async Endpoint',
      'target_entity_type' => 'user',
      'source_types' => ['async_source'],
      'processing_mode' => 'async',
    ])->save();

    WebhookSourceType::create([
      'id' => 'async_source',
      'label' => 'Async Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    $request = $this->buildPostRequest(
      '/webhook/async_endpoint/async_source',
      json_encode(['key' => 'value']),
    );

    // Act
    $response = $this->processRequest($request);

    // Assert
    $this->assertSame(200, $response->getStatusCode());
    $body = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('queued', $body['status']);

    /** @var \Drupal\entity_webhook\Queue\WebhookQueueService $queueService */
    $queueService = $this->container->get('entity_webhook.webhook_queue');
    $this->assertSame(1, $queueService->getQueueDepth());
  }

  /**
   * Tests that a sync endpoint does not enqueue and returns a processing result.
   *
   * The processor will return an error result (no real entity pipeline in kernel
   * bootstrap) but the key assertion is that nothing was queued and the
   * response body contains a 'status' key (either 'success' or 'error').
   */
  public function testSyncEndpointDoesNotEnqueuePayload(): void {
    // Arrange
    WebhookEndpoint::create([
      'id' => 'sync_endpoint',
      'label' => 'Sync Endpoint',
      'target_entity_type' => 'user',
      'source_types' => ['sync_source'],
      'processing_mode' => 'sync',
    ])->save();

    WebhookSourceType::create([
      'id' => 'sync_source',
      'label' => 'Sync Source',
      'verification_plugin' => '',
      'verification_config' => [],
      'operation' => 'upsert',
    ])->save();

    $request = $this->buildPostRequest(
      '/webhook/sync_endpoint/sync_source',
      json_encode(['key' => 'value']),
    );

    // Act
    $response = $this->processRequest($request);

    // Assert - payload must NOT be queued regardless of processing outcome
    /** @var \Drupal\entity_webhook\Queue\WebhookQueueService $queueService */
    $queueService = $this->container->get('entity_webhook.webhook_queue');
    $this->assertSame(0, $queueService->getQueueDepth());

    $body = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertArrayHasKey('status', $body);
  }

  /**
   * Builds a POST request with JSON content type.
   *
   * @param string $uri
   *   The request URI.
   * @param string $content
   *   The raw request body.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The constructed request.
   */
  private function buildPostRequest(string $uri, string $content): Request {
    $request = Request::create($uri, 'POST', [], [], [], [], $content);
    $request->headers->set('Content-Type', 'application/json');

    return $request;
  }

  /**
   * Processes a request through Drupal's HTTP kernel.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request to process.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response.
   */
  private function processRequest(Request $request): \Symfony\Component\HttpFoundation\Response {
    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel */
    $httpKernel = $this->container->get('http_kernel');

    return $httpKernel->handle($request);
  }

}
