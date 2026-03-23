<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

/**
 * Computes exponential backoff retry schedules for failed webhook deliveries.
 *
 * Uses the formula: delay = baseDelay * 2^(attempt - 1).
 * Jitter is not applied by default; the delay grows deterministically so
 * that retry timestamps are predictable and testable.
 */
class RetryScheduler implements RetrySchedulerInterface {

  /**
   * {@inheritdoc}
   */
  public function shouldRetry(int $attempt, int $maxAttempts): bool {
    return $attempt < $maxAttempts;
  }

  /**
   * {@inheritdoc}
   */
  public function nextRetryAt(int $attempt, int $baseDelay): \DateTimeImmutable {
    $delaySeconds = $this->computeDelay($attempt, $baseDelay);
    return new \DateTimeImmutable("+{$delaySeconds} seconds");
  }

  /**
   * Computes the delay in seconds using exponential backoff.
   *
   * @param int $attempt
   *   The attempt number that just completed (1-based).
   * @param int $baseDelay
   *   The base delay in seconds.
   *
   * @return int
   *   The delay in seconds before the next attempt.
   */
  private function computeDelay(int $attempt, int $baseDelay): int {
    return (int) ($baseDelay * (2 ** ($attempt - 1)));
  }

}
