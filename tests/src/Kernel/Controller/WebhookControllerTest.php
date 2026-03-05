<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Controller;

use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for WebhookController HTTP responses.
 *
 * @group entity_webhook
 */
class WebhookControllerTest extends KernelTestBase {

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

    WebhookEndpoint::create([
      'id' => 'test_endpoint',
      'label' => 'Test Endpoint',
      'target_entity_type' => 'user',
      'source_types' => ['test_source'],
    ])->save();

    WebhookSourceType::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();
  }

  /**
   * Tests that a valid POST request with JSON payload returns 200.
   */
  public function testValidPayloadReturns200(): void {
    $request = $this->buildPostRequest(
      '/webhook/test_endpoint/test_source',
      json_encode(['key' => 'value']),
    );

    $response = $this->processRequest($request);

    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests that an invalid JSON body returns 400.
   */
  public function testInvalidJsonReturns400(): void {
    $request = $this->buildPostRequest(
      '/webhook/test_endpoint/test_source',
      'not-valid-json{{{',
    );

    $response = $this->processRequest($request);

    $this->assertSame(400, $response->getStatusCode());
  }

  /**
   * Tests that an unknown endpoint name returns 404.
   */
  public function testUnknownEndpointReturns404(): void {
    $request = $this->buildPostRequest(
      '/webhook/nonexistent_endpoint/test_source',
      json_encode(['key' => 'value']),
    );

    $response = $this->processRequest($request);

    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * Tests that an unknown source type returns 404.
   */
  public function testUnknownSourceTypeReturns404(): void {
    $request = $this->buildPostRequest(
      '/webhook/test_endpoint/nonexistent_source',
      json_encode(['key' => 'value']),
    );

    $response = $this->processRequest($request);

    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * Tests that a source type not associated with the endpoint returns 404.
   */
  public function testSourceTypeNotAssociatedWithEndpointReturns404(): void {
    WebhookSourceType::create([
      'id' => 'unassociated_source',
      'label' => 'Unassociated Source',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    $request = $this->buildPostRequest(
      '/webhook/test_endpoint/unassociated_source',
      json_encode(['key' => 'value']),
    );

    $response = $this->processRequest($request);

    $this->assertSame(404, $response->getStatusCode());
  }

  /**
   * Tests that a request fails with 403 when HMAC verification is configured and signature is wrong.
   */
  public function testFailedVerificationReturns403(): void {
    WebhookSourceType::create([
      'id' => 'secured_source',
      'label' => 'Secured Source',
      'field_mappings' => [],
      'verification_plugin' => 'hmac_verification',
      'verification_config' => [
        'secret' => 'correct-secret',
        'header' => 'X-Hub-Signature-256',
      ],
    ])->save();

    WebhookEndpoint::create([
      'id' => 'secured_endpoint',
      'label' => 'Secured Endpoint',
      'target_entity_type' => 'user',
      'source_types' => ['secured_source'],
    ])->save();

    $request = $this->buildPostRequest(
      '/webhook/secured_endpoint/secured_source',
      json_encode(['event' => 'created']),
    );
    $request->headers->set('X-Hub-Signature-256', 'sha256=wrongsignature');

    $response = $this->processRequest($request);

    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Tests that a request passes when HMAC verification succeeds.
   */
  public function testPassedVerificationReturns200(): void {
    $secret = 'correct-secret';
    $body = json_encode(['event' => 'created']);
    $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

    WebhookSourceType::create([
      'id' => 'hmac_source',
      'label' => 'HMAC Source',
      'field_mappings' => [],
      'verification_plugin' => 'hmac_verification',
      'verification_config' => [
        'secret' => $secret,
        'header' => 'X-Hub-Signature-256',
      ],
    ])->save();

    WebhookEndpoint::create([
      'id' => 'hmac_endpoint',
      'label' => 'HMAC Endpoint',
      'target_entity_type' => 'user',
      'source_types' => ['hmac_source'],
    ])->save();

    $request = $this->buildPostRequest('/webhook/hmac_endpoint/hmac_source', $body);
    $request->headers->set('X-Hub-Signature-256', $signature);

    $response = $this->processRequest($request);

    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests that a valid request queues the payload for processing.
   */
  public function testValidPayloadIsQueued(): void {
    $payload = ['event' => 'created', 'id' => 42];
    $request = $this->buildPostRequest(
      '/webhook/test_endpoint/test_source',
      json_encode($payload),
    );

    $this->processRequest($request);

    /** @var \Drupal\entity_webhook\Queue\WebhookQueueServiceInterface $queueService */
    $queueService = $this->container->get('entity_webhook.webhook_queue');
    $this->assertSame(1, $queueService->getQueueDepth());
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
