<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Service;

use Drupal\entity_webhook_broadcast\Service\RetryScheduler;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the RetryScheduler service.
 *
 * Verifies exponential backoff calculation and retry eligibility boundary
 * conditions. The formula under test is: delay = baseDelay * 2^(attempt - 1).
 *
 * @group entity_webhook_broadcast
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Service\RetryScheduler
 */
class RetrySchedulerTest extends UnitTestCase {

  /**
   * The service under test.
   */
  private RetryScheduler $scheduler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->scheduler = new RetryScheduler();
  }

  // ---------------------------------------------------------------------------
  // shouldRetry() boundary conditions
  // ---------------------------------------------------------------------------

  /**
   * Tests that shouldRetry returns TRUE when attempt is below maxAttempts.
   */
  public function testShouldRetryReturnsTrueWhenAttemptIsBelowMaxAttempts(): void {
    // Arrange
    $attempt = 1;
    $maxAttempts = 5;

    // Act
    $result = $this->scheduler->shouldRetry($attempt, $maxAttempts);

    // Assert
    $this->assertTrue($result);
  }

  /**
   * Tests that shouldRetry returns FALSE when attempt equals maxAttempts.
   *
   * At the boundary (attempt == maxAttempts) no further retry should occur.
   */
  public function testShouldRetryReturnsFalseWhenAttemptEqualsMaxAttempts(): void {
    // Arrange
    $attempt = 5;
    $maxAttempts = 5;

    // Act
    $result = $this->scheduler->shouldRetry($attempt, $maxAttempts);

    // Assert
    $this->assertFalse($result);
  }

  /**
   * Tests that shouldRetry returns FALSE when attempt exceeds maxAttempts.
   *
   * Guards against a scenario where the attempt counter drifts past the limit.
   */
  public function testShouldRetryReturnsFalseWhenAttemptExceedsMaxAttempts(): void {
    // Arrange
    $attempt = 7;
    $maxAttempts = 5;

    // Act
    $result = $this->scheduler->shouldRetry($attempt, $maxAttempts);

    // Assert
    $this->assertFalse($result);
  }

  /**
   * Tests that shouldRetry returns TRUE for attempt 1 with maxAttempts of 2.
   */
  public function testShouldRetryReturnsTrueForFirstAttemptWithTwoMaxAttempts(): void {
    // Arrange
    $attempt = 1;
    $maxAttempts = 2;

    // Act
    $result = $this->scheduler->shouldRetry($attempt, $maxAttempts);

    // Assert
    $this->assertTrue($result);
  }

  /**
   * Tests that shouldRetry with maxAttempts=1 always returns FALSE on attempt 1.
   *
   * A maxAttempts of 1 means only the initial delivery is allowed; no retries.
   */
  public function testShouldRetryReturnsFalseWhenMaxAttemptsIsOneAndAttemptIsOne(): void {
    // Arrange
    $attempt = 1;
    $maxAttempts = 1;

    // Act
    $result = $this->scheduler->shouldRetry($attempt, $maxAttempts);

    // Assert
    $this->assertFalse($result);
  }

  // ---------------------------------------------------------------------------
  // nextRetryAt() exponential backoff math
  // ---------------------------------------------------------------------------

  /**
   * Tests that attempt 1 produces a delay equal to the base delay.
   *
   * Formula: baseDelay * 2^(1 - 1) = baseDelay * 1 = baseDelay.
   */
  public function testNextRetryAtAttemptOneProducesBaseDelaySeconds(): void {
    // Arrange
    $baseDelay = 60;
    $expectedDelay = 60; // 60 * 2^0 = 60

    $before = new \DateTimeImmutable();

    // Act
    $result = $this->scheduler->nextRetryAt(1, $baseDelay);

    $after = new \DateTimeImmutable("+{$expectedDelay} seconds");

    // Assert: result timestamp is within baseDelay seconds from now
    $this->assertGreaterThanOrEqual(
      $before->getTimestamp() + $expectedDelay - 1,
      $result->getTimestamp(),
    );
    $this->assertLessThanOrEqual(
      $after->getTimestamp() + 1,
      $result->getTimestamp(),
    );
  }

  /**
   * Tests that attempt 2 produces double the base delay.
   *
   * Formula: baseDelay * 2^(2 - 1) = baseDelay * 2.
   */
  public function testNextRetryAtAttemptTwoProducesTwoTimesBaseDelay(): void {
    // Arrange
    $baseDelay = 60;
    $expectedDelay = 120; // 60 * 2^1 = 120

    $before = new \DateTimeImmutable();

    // Act
    $result = $this->scheduler->nextRetryAt(2, $baseDelay);

    $after = new \DateTimeImmutable("+{$expectedDelay} seconds");

    // Assert
    $this->assertGreaterThanOrEqual(
      $before->getTimestamp() + $expectedDelay - 1,
      $result->getTimestamp(),
    );
    $this->assertLessThanOrEqual(
      $after->getTimestamp() + 1,
      $result->getTimestamp(),
    );
  }

  /**
   * Tests that attempt 3 produces four times the base delay.
   *
   * Formula: baseDelay * 2^(3 - 1) = baseDelay * 4.
   */
  public function testNextRetryAtAttemptThreeProducesFourTimesBaseDelay(): void {
    // Arrange
    $baseDelay = 60;
    $expectedDelay = 240; // 60 * 2^2 = 240

    $before = new \DateTimeImmutable();

    // Act
    $result = $this->scheduler->nextRetryAt(3, $baseDelay);

    $after = new \DateTimeImmutable("+{$expectedDelay} seconds");

    // Assert
    $this->assertGreaterThanOrEqual(
      $before->getTimestamp() + $expectedDelay - 1,
      $result->getTimestamp(),
    );
    $this->assertLessThanOrEqual(
      $after->getTimestamp() + 1,
      $result->getTimestamp(),
    );
  }

  /**
   * Tests the backoff progression using computed expected values.
   *
   * Verifies that consecutive attempts produce the correct doubling sequence.
   * Each attempt N produces: baseDelay * 2^(N-1).
   *
   * @param int $attempt
   *   The attempt number (1-based).
   * @param int $baseDelay
   *   The base delay in seconds.
   * @param int $expectedDelay
   *   The expected delay in seconds.
   *
   * @dataProvider provideBackoffCalculations
   */
  public function testNextRetryAtProducesCorrectExponentialDelay(
    int $attempt,
    int $baseDelay,
    int $expectedDelay,
  ): void {
    // Arrange
    $before = new \DateTimeImmutable();

    // Act
    $result = $this->scheduler->nextRetryAt($attempt, $baseDelay);

    // Assert: the returned timestamp falls within [now + delay - 1, now + delay + 1]
    // to account for sub-second execution time.
    $this->assertGreaterThanOrEqual(
      $before->getTimestamp() + $expectedDelay - 1,
      $result->getTimestamp(),
      "Attempt {$attempt} with base {$baseDelay}s should delay at least {$expectedDelay}s.",
    );
    $this->assertLessThanOrEqual(
      $before->getTimestamp() + $expectedDelay + 1,
      $result->getTimestamp(),
      "Attempt {$attempt} with base {$baseDelay}s should delay at most {$expectedDelay}s.",
    );
  }

  /**
   * Data provider for exponential backoff calculation tests.
   *
   * @return array<string, array{int, int, int}>
   */
  public static function provideBackoffCalculations(): array {
    return [
      'attempt 1 with base 30s gives 30s delay' => [1, 30, 30],
      'attempt 2 with base 30s gives 60s delay' => [2, 30, 60],
      'attempt 3 with base 30s gives 120s delay' => [3, 30, 120],
      'attempt 4 with base 30s gives 240s delay' => [4, 30, 240],
      'attempt 5 with base 30s gives 480s delay' => [5, 30, 480],
      'attempt 1 with base 60s gives 60s delay' => [1, 60, 60],
      'attempt 2 with base 60s gives 120s delay' => [2, 60, 120],
      'attempt 3 with base 60s gives 240s delay' => [3, 60, 240],
    ];
  }

  /**
   * Tests that nextRetryAt returns a DateTimeImmutable instance.
   */
  public function testNextRetryAtReturnsDateTimeImmutable(): void {
    // Arrange + Act
    $result = $this->scheduler->nextRetryAt(1, 60);

    // Assert
    $this->assertInstanceOf(\DateTimeImmutable::class, $result);
  }

  /**
   * Tests that nextRetryAt returns a timestamp in the future.
   */
  public function testNextRetryAtReturnsFutureTimestamp(): void {
    // Arrange
    $now = new \DateTimeImmutable();

    // Act
    $result = $this->scheduler->nextRetryAt(1, 60);

    // Assert
    $this->assertGreaterThan($now->getTimestamp(), $result->getTimestamp());
  }

}
