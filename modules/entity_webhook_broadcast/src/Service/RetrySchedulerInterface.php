<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

/**
 * Determines retry eligibility and computes exponential backoff timestamps.
 *
 * The retry scheduler encapsulates all retry policy decisions, allowing the
 * queue worker and retry processor to delegate scheduling logic cleanly.
 */
interface RetrySchedulerInterface {

  /**
   * Returns TRUE if another delivery attempt should be made.
   *
   * @param int $attempt
   *   The attempt number that just completed (1-based).
   * @param int $maxAttempts
   *   The maximum allowed attempts for this delivery.
   *
   * @return bool
   *   TRUE if attempt < maxAttempts, FALSE otherwise.
   */
  public function shouldRetry(int $attempt, int $maxAttempts): bool;

  /**
   * Computes the timestamp for the next retry using exponential backoff.
   *
   * The delay formula is: baseDelay * 2^(attempt - 1).
   * For example, with baseDelay=60 seconds:
   *   - attempt 1: 60s delay
   *   - attempt 2: 120s delay
   *   - attempt 3: 240s delay
   *
   * @param int $attempt
   *   The attempt number that just completed (1-based).
   * @param int $baseDelay
   *   The base delay in seconds.
   *
   * @return \DateTimeImmutable
   *   The point in time when the next retry should occur.
   */
  public function nextRetryAt(int $attempt, int $baseDelay): \DateTimeImmutable;

}
