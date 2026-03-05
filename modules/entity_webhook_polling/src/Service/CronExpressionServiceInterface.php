<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Service;

/**
 * Evaluates cron expressions to determine whether polling is due.
 */
interface CronExpressionServiceInterface {

  /**
   * Determines whether a cron expression is currently due.
   *
   * @param string $expression
   *   A standard cron expression (e.g., "* /5 * * * *").
   * @param \DateTimeImmutable|null $referenceTime
   *   The time to evaluate against. Defaults to the current time when NULL.
   *
   * @return bool
   *   TRUE if the expression matches the given time, FALSE otherwise.
   */
  public function isDue(string $expression, ?\DateTimeImmutable $referenceTime = NULL): bool;

  /**
   * Checks whether the given string is a syntactically valid cron expression.
   *
   * @param string $expression
   *   The cron expression string to validate.
   *
   * @return bool
   *   TRUE if the expression is valid, FALSE otherwise.
   */
  public function isValid(string $expression): bool;

}
