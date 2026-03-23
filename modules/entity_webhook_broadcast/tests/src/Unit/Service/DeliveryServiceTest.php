<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Service;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\entity_webhook_broadcast\Service\DeliveryResult;
use Drupal\entity_webhook_broadcast\Service\DeliveryService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\StreamInterface;

/**
 * Unit tests for the DeliveryService.
 *
 * Verifies HTTP POST behavior, HMAC signature header computation,
 * and error handling for all transport failure scenarios.
 *
 * @group entity_webhook_broadcast
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Service\DeliveryService
 */
class DeliveryServiceTest extends UnitTestCase {

  /**
   * The mocked Guzzle HTTP client.
   */
  private ClientInterface $httpClient;

  /**
   * The mocked logger channel.
   */
  private LoggerChannelInterface $logger;

  /**
   * The service under test.
   */
  private DeliveryService $deliveryService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->deliveryService = new DeliveryService($this->httpClient, $this->logger);
  }

  /**
   * Tests that deliver returns a successful result for a 200 response.
   *
   * @covers ::deliver
   */
  public function testDeliverReturnsSuccessResultFor200Response(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'none',
    );

    $response = $this->createResponseMock(200, '{"received":true}');
    $this->httpClient->method('request')->willReturn($response);

    // Act
    $result = $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert
    $this->assertTrue($result->success);
    $this->assertSame(200, $result->httpStatus);
  }

  /**
   * Tests that deliver returns a successful result for a 201 response.
   *
   * @covers ::deliver
   */
  public function testDeliverReturnsSuccessResultFor201Response(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'none',
    );

    $response = $this->createResponseMock(201, '');
    $this->httpClient->method('request')->willReturn($response);

    // Act
    $result = $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert
    $this->assertTrue($result->success);
    $this->assertSame(201, $result->httpStatus);
  }

  /**
   * Tests that deliver returns a failed result for a non-2xx HTTP response.
   *
   * @covers ::deliver
   */
  public function testDeliverReturnsHttpFailureResultForNon2xxResponse(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'none',
    );

    $response = $this->createResponseMock(503, 'Service Unavailable');
    $this->httpClient->method('request')->willReturn($response);

    // Act
    $result = $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert
    $this->assertFalse($result->success);
    $this->assertSame(503, $result->httpStatus);
    $this->assertSame('HTTP 503', $result->errorMessage);
  }

  /**
   * Tests that deliver issues an HTTP POST to the subscription URL.
   *
   * @covers ::deliver
   */
  public function testDeliverPostsToSubscriptionUrl(): void {
    // Arrange
    $targetUrl = 'https://receiver.example.com/hooks/abc123';
    $subscription = $this->createSubscriptionMock(
      url: $targetUrl,
      secret: NULL,
      signingAlgorithm: 'none',
    );

    $response = $this->createResponseMock(200, '');

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('POST', $targetUrl, $this->anything())
      ->willReturn($response);

    // Act
    $this->deliveryService->deliver($subscription, ['event' => 'insert']);
  }

  /**
   * Tests that deliver sets the Content-Type header to application/json.
   *
   * @covers ::deliver
   */
  public function testDeliverSetsContentTypeHeader(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'none',
    );

    $response = $this->createResponseMock(200, '');
    $capturedOptions = [];

    $this->httpClient->expects($this->once())
      ->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use ($response, &$capturedOptions): Response {
        $capturedOptions = $options;
        return $response;
      });

    // Act
    $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert
    $this->assertArrayHasKey('headers', $capturedOptions);
    $this->assertSame('application/json', $capturedOptions['headers']['Content-Type']);
  }

  /**
   * Tests that deliver sends the payload as a JSON-encoded body.
   *
   * @covers ::deliver
   */
  public function testDeliverSendsJsonEncodedPayloadAsBody(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'none',
    );

    $payload = ['event' => 'insert', 'entity_type' => 'node', 'id' => 42];
    $expectedBody = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    $response = $this->createResponseMock(200, '');
    $capturedOptions = [];

    $this->httpClient->expects($this->once())
      ->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use ($response, &$capturedOptions): Response {
        $capturedOptions = $options;
        return $response;
      });

    // Act
    $this->deliveryService->deliver($subscription, $payload);

    // Assert
    $this->assertSame($expectedBody, $capturedOptions['body']);
  }

  /**
   * Tests that deliver includes an HMAC signature header when secret is set.
   *
   * @covers ::deliver
   */
  public function testDeliverIncludesHmacSignatureHeaderWhenSecretIsConfigured(): void {
    // Arrange
    $secret = 'my-signing-secret';
    $algorithm = 'sha256';
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: $secret,
      signingAlgorithm: $algorithm,
    );

    $payload = ['event' => 'insert', 'id' => 1];
    $response = $this->createResponseMock(200, '');
    $capturedOptions = [];

    $this->httpClient->expects($this->once())
      ->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use ($response, &$capturedOptions): Response {
        $capturedOptions = $options;
        return $response;
      });

    // Act
    $this->deliveryService->deliver($subscription, $payload);

    // Assert: X-Webhook-Signature header is present.
    $this->assertArrayHasKey('X-Webhook-Signature', $capturedOptions['headers']);

    // Verify the HMAC signature is correct by recomputing it.
    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $expectedSignature = hash_hmac($algorithm, $body, $secret);
    $this->assertSame($expectedSignature, $capturedOptions['headers']['X-Webhook-Signature']);
  }

  /**
   * Tests that deliver omits the HMAC header when signing algorithm is 'none'.
   *
   * @covers ::deliver
   */
  public function testDeliverOmitsSignatureHeaderWhenAlgorithmIsNone(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: 'some-secret',
      signingAlgorithm: 'none',
    );

    $response = $this->createResponseMock(200, '');
    $capturedOptions = [];

    $this->httpClient->expects($this->once())
      ->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use ($response, &$capturedOptions): Response {
        $capturedOptions = $options;
        return $response;
      });

    // Act
    $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert: no signature header.
    $this->assertArrayNotHasKey('X-Webhook-Signature', $capturedOptions['headers']);
  }

  /**
   * Tests that deliver omits the HMAC header when no secret is configured.
   *
   * @covers ::deliver
   */
  public function testDeliverOmitsSignatureHeaderWhenNoSecretConfigured(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'sha256',
    );

    $response = $this->createResponseMock(200, '');
    $capturedOptions = [];

    $this->httpClient->expects($this->once())
      ->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use ($response, &$capturedOptions): Response {
        $capturedOptions = $options;
        return $response;
      });

    // Act
    $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert
    $this->assertArrayNotHasKey('X-Webhook-Signature', $capturedOptions['headers']);
  }

  /**
   * Tests that deliver computes different signatures for different secrets.
   *
   * @covers ::deliver
   */
  public function testDeliverComputesDifferentSignaturesForDifferentSecrets(): void {
    // Arrange
    $payload = ['event' => 'update', 'id' => 5];
    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    $signatureOne = NULL;
    $signatureTwo = NULL;

    $subscriptionOne = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: 'secret-one',
      signingAlgorithm: 'sha256',
    );
    $subscriptionTwo = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: 'secret-two',
      signingAlgorithm: 'sha256',
    );

    $capturedOptionsOne = [];
    $capturedOptionsTwo = [];

    $this->httpClient->expects($this->exactly(2))
      ->method('request')
      ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptionsOne, &$capturedOptionsTwo): Response {
        static $callCount = 0;
        $callCount++;
        if ($callCount === 1) {
          $capturedOptionsOne = $options;
        }
        else {
          $capturedOptionsTwo = $options;
        }
        return $this->createResponseMock(200, '');
      });

    // Act
    $this->deliveryService->deliver($subscriptionOne, $payload);
    $this->deliveryService->deliver($subscriptionTwo, $payload);

    // Assert: signatures differ between secrets.
    $signatureOne = $capturedOptionsOne['headers']['X-Webhook-Signature'] ?? NULL;
    $signatureTwo = $capturedOptionsTwo['headers']['X-Webhook-Signature'] ?? NULL;

    $this->assertNotNull($signatureOne);
    $this->assertNotNull($signatureTwo);
    $this->assertNotSame($signatureOne, $signatureTwo);

    // Verify each is independently correct.
    $this->assertSame(hash_hmac('sha256', $body, 'secret-one'), $signatureOne);
    $this->assertSame(hash_hmac('sha256', $body, 'secret-two'), $signatureTwo);
  }

  /**
   * Tests that deliver returns a connection failure result when Guzzle throws.
   *
   * @covers ::deliver
   */
  public function testDeliverReturnsConnectionFailureWhenGuzzleThrows(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://unreachable.example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'none',
    );

    $guzzleRequest = new Request('POST', 'https://unreachable.example.com/webhook');
    $exception = new ConnectException('cURL error 6: Could not resolve host', $guzzleRequest);

    $this->httpClient->method('request')->willThrowException($exception);
    $this->logger->expects($this->once())->method('error');

    // Act
    $result = $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert
    $this->assertFalse($result->success);
    $this->assertNull($result->httpStatus);
    $this->assertStringContainsString('Could not resolve host', $result->errorMessage ?? '');
  }

  /**
   * Tests that deliver returns a failed result and logs when a request exception occurs.
   *
   * @covers ::deliver
   */
  public function testDeliverLogsErrorAndReturnsFailureOnRequestException(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'none',
    );

    $guzzleRequest = new Request('POST', 'https://example.com/webhook');
    $exception = new RequestException('Connection timed out', $guzzleRequest);

    $this->httpClient->method('request')->willThrowException($exception);
    $this->logger->expects($this->once())->method('error');

    // Act
    $result = $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert
    $this->assertFalse($result->success);
    $this->assertNull($result->httpStatus);
    $this->assertNotNull($result->errorMessage);
  }

  /**
   * Tests that deliver truncates the response body to 1024 bytes.
   *
   * @covers ::deliver
   */
  public function testDeliverTruncatesResponseBodyTo1024Bytes(): void {
    // Arrange
    $subscription = $this->createSubscriptionMock(
      url: 'https://example.com/webhook',
      secret: NULL,
      signingAlgorithm: 'none',
    );

    // 2000-byte body exceeding the 1024-byte limit.
    $largeBody = str_repeat('x', 2000);
    $response = $this->createResponseMock(200, $largeBody);
    $this->httpClient->method('request')->willReturn($response);

    // Act
    $result = $this->deliveryService->deliver($subscription, ['event' => 'insert']);

    // Assert: stored body is capped at 1024 characters.
    $this->assertNotNull($result->responseBody);
    $this->assertSame(1024, strlen($result->responseBody));
  }

  /**
   * Creates a mock OutboundSubscriptionInterface.
   *
   * @param string $url
   *   The destination URL.
   * @param string|null $secret
   *   The HMAC secret (NULL if signing disabled).
   * @param string $signingAlgorithm
   *   The signing algorithm ('sha256', 'sha1', or 'none').
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface
   *   The mock subscription.
   */
  private function createSubscriptionMock(
    string $url,
    ?string $secret,
    string $signingAlgorithm,
  ): OutboundSubscriptionInterface {
    $subscription = $this->createMock(OutboundSubscriptionInterface::class);
    $subscription->method('getUrl')->willReturn($url);
    $subscription->method('getSecret')->willReturn($secret);
    $subscription->method('getSigningAlgorithm')->willReturn($signingAlgorithm);
    return $subscription;
  }

  /**
   * Creates a mock Guzzle response with the given status code and body.
   *
   * @param int $statusCode
   *   The HTTP status code.
   * @param string $body
   *   The response body string.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   The Guzzle response mock.
   */
  private function createResponseMock(int $statusCode, string $body): Response {
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('__toString')->willReturn($body);

    return new Response(
      status: $statusCode,
      headers: ['Content-Type' => 'application/json'],
      body: $body,
    );
  }

}
