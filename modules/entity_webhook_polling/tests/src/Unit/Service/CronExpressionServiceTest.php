<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_polling\Unit\Service;

use Drupal\entity_webhook_polling\Service\CronExpressionService;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for CronExpressionService.
 *
 * @group entity_webhook_polling
 */
class CronExpressionServiceTest extends UnitTestCase {

  /**
   * The cron expression service under test.
   */
  private CronExpressionService $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->service = new CronExpressionService();
  }

  /**
   * Tests that a cron expression that is due returns true.
   *
   * The expression "* * * * *" (every minute) is always due.
   */
  public function testIsDueReturnsTrueForEveryMinuteExpression(): void {
    $result = $this->service->isDue('* * * * *');

    $this->assertTrue($result);
  }

  /**
   * Tests that a cron expression far in the future is not due.
   *
   * February 31 does not exist, so this expression is never due.
   * We use a specific date in a past year as the reference time to ensure
   * the expression is not due.
   */
  public function testIsDueReturnsFalseWhenExpressionIsNotDue(): void {
    // January 1 at midnight: only matches expression "0 0 1 1 *" (Jan 1 at midnight).
    // Check whether "0 0 1 1 *" is due at a non-matching time (e.g. Jan 2 at noon).
    $referenceTime = new \DateTimeImmutable('2024-01-02 12:00:00');

    $result = $this->service->isDue('0 0 1 1 *', $referenceTime);

    $this->assertFalse($result);
  }

  /**
   * Tests that isDue uses the reference time when provided.
   *
   * "0 0 1 1 *" matches exactly January 1 at midnight (00:00).
   */
  public function testIsDueUsesReferenceTimeWhenProvided(): void {
    $referenceTime = new \DateTimeImmutable('2024-01-01 00:00:00');

    $result = $this->service->isDue('0 0 1 1 *', $referenceTime);

    $this->assertTrue($result);
  }

  /**
   * Tests that isValid returns true for a valid cron expression.
   */
  public function testIsValidReturnsTrueForValidExpression(): void {
    $result = $this->service->isValid('*/5 * * * *');

    $this->assertTrue($result);
  }

  /**
   * Tests that isValid returns false for an invalid cron expression.
   */
  public function testIsValidReturnsFalseForInvalidExpression(): void {
    $result = $this->service->isValid('not_a_cron_expression');

    $this->assertFalse($result);
  }

}
