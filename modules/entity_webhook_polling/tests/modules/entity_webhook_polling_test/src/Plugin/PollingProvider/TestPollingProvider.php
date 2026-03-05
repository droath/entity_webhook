<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling_test\Plugin\PollingProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_webhook_polling\Attribute\PollingProvider;
use Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a test polling provider plugin for kernel tests.
 *
 * Returns a fixed set of payloads so that kernel tests can verify the
 * polling manager's hash detection and queuing logic without a real
 * external HTTP dependency.
 */
#[PollingProvider(
  id: 'test_polling_provider',
  label: new TranslatableMarkup('Test Polling Provider'),
)]
class TestPollingProvider extends PollingProviderBase {

  /**
   * The static payloads to return from fetch().
   *
   * Tests may override this via setPayloads() before triggering polling.
   *
   * @var array<int, array<string, mixed>>
   */
  private static array $payloads = [];

  /**
   * Overrides the payloads returned by fetch() for a single test run.
   *
   * @param array<int, array<string, mixed>> $payloads
   *   The payloads to return from fetch().
   */
  public static function setPayloads(array $payloads): void {
    self::$payloads = $payloads;
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): array {
    return self::$payloads;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

}
