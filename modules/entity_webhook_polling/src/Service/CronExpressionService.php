<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Service;

use Cron\CronExpression;

/**
 * Evaluates cron expressions using dragonmantank/cron-expression.
 */
class CronExpressionService implements CronExpressionServiceInterface {

  /**
   * {@inheritdoc}
   */
  public function isDue(string $expression, ?\DateTimeImmutable $referenceTime = NULL): bool {
    try {
      $cron = new CronExpression($expression);

      if ($referenceTime !== NULL) {
        return $cron->isDue(\DateTime::createFromImmutable($referenceTime));
      }

      return $cron->isDue();
    }
    catch (\InvalidArgumentException) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(string $expression): bool {
    return CronExpression::isValidExpression($expression);
  }

}
