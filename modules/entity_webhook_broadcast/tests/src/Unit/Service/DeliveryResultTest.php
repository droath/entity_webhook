<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Service;

use Drupal\entity_webhook_broadcast\Service\DeliveryResult;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the DeliveryResult value object.
 *
 * @group entity_webhook_broadcast
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Service\DeliveryResult
 */
class DeliveryResultTest extends UnitTestCase {

  /**
   * Tests that success() creates a result with success=true and no error message.
   *
   * @covers ::success
   */
  public function testSuccessFactoryCreatesSuccessfulResult(): void {
    // Act
    $result = DeliveryResult::success(200, '{"ok":true}');

    // Assert
    $this->assertTrue($result->success);
    $this->assertSame(200, $result->httpStatus);
    $this->assertSame('{"ok":true}', $result->responseBody);
    $this->assertNull($result->errorMessage);
  }

  /**
   * Tests that success() accepts a null response body.
   *
   * @covers ::success
   */
  public function testSuccessFactoryAcceptsNullResponseBody(): void {
    // Act
    $result = DeliveryResult::success(201);

    // Assert
    $this->assertTrue($result->success);
    $this->assertSame(201, $result->httpStatus);
    $this->assertNull($result->responseBody);
    $this->assertNull($result->errorMessage);
  }

  /**
   * Tests that httpFailure() creates a failed result with the HTTP status code in the error message.
   *
   * @covers ::httpFailure
   */
  public function testHttpFailureFactoryCreatesFailedResultWithStatusInErrorMessage(): void {
    // Act
    $result = DeliveryResult::httpFailure(503, 'Service Unavailable');

    // Assert
    $this->assertFalse($result->success);
    $this->assertSame(503, $result->httpStatus);
    $this->assertSame('Service Unavailable', $result->responseBody);
    $this->assertSame('HTTP 503', $result->errorMessage);
  }

  /**
   * Tests that httpFailure() accepts a null response body.
   *
   * @covers ::httpFailure
   */
  public function testHttpFailureFactoryAcceptsNullResponseBody(): void {
    // Act
    $result = DeliveryResult::httpFailure(404);

    // Assert
    $this->assertFalse($result->success);
    $this->assertSame(404, $result->httpStatus);
    $this->assertNull($result->responseBody);
    $this->assertSame('HTTP 404', $result->errorMessage);
  }

  /**
   * Tests that connectionFailure() creates a failed result with no HTTP status.
   *
   * @covers ::connectionFailure
   */
  public function testConnectionFailureFactoryCreatesFailedResultWithNoHttpStatus(): void {
    // Arrange
    $errorMessage = 'cURL error 6: Could not resolve host';

    // Act
    $result = DeliveryResult::connectionFailure($errorMessage);

    // Assert
    $this->assertFalse($result->success);
    $this->assertNull($result->httpStatus);
    $this->assertNull($result->responseBody);
    $this->assertSame($errorMessage, $result->errorMessage);
  }

  /**
   * Tests that the constructor allows direct instantiation with all fields.
   *
   * @covers ::__construct
   */
  public function testConstructorAllowsDirectInstantiationWithAllFields(): void {
    // Act
    $result = new DeliveryResult(
      success: FALSE,
      httpStatus: 429,
      responseBody: 'Too Many Requests',
      errorMessage: 'Rate limit exceeded',
    );

    // Assert
    $this->assertFalse($result->success);
    $this->assertSame(429, $result->httpStatus);
    $this->assertSame('Too Many Requests', $result->responseBody);
    $this->assertSame('Rate limit exceeded', $result->errorMessage);
  }

  /**
   * Tests that the value object is immutable (readonly class).
   */
  public function testValueObjectIsImmutable(): void {
    // Arrange
    $result = DeliveryResult::success(200);

    // Assert: readonly properties cannot be modified
    $this->expectException(\Error::class);
    // @phpstan-ignore-next-line
    $result->success = FALSE;
  }

  /**
   * Tests that httpFailure() error message format includes the exact status code.
   *
   * @dataProvider httpFailureStatusCodeProvider
   * @covers ::httpFailure
   */
  public function testHttpFailureErrorMessageContainsExactStatusCode(int $statusCode): void {
    // Act
    $result = DeliveryResult::httpFailure($statusCode);

    // Assert
    $this->assertSame("HTTP {$statusCode}", $result->errorMessage);
  }

  /**
   * Data provider for HTTP failure status codes.
   *
   * @return array<string, array{int}>
   */
  public static function httpFailureStatusCodeProvider(): array {
    return [
      '400 Bad Request' => [400],
      '401 Unauthorized' => [401],
      '403 Forbidden' => [403],
      '404 Not Found' => [404],
      '500 Internal Server Error' => [500],
      '503 Service Unavailable' => [503],
    ];
  }

}
