<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Service;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Psr\Log\LoggerInterface;

/**
 * Kernel tests verifying WebhookProcessor logging behavior.
 *
 * @group entity_webhook
 */
class WebhookProcessorLoggingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'node',
    'user',
    'field',
    'text',
    'filter',
    'system',
  ];

  /**
   * Shared log store for capturing messages during the test.
   */
  private LogStore $logStore;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'filter']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    WebhookEndpoint::create([
      'id' => 'log_endpoint',
      'label' => 'Log Endpoint',
      'target_entity_type' => 'node',
      'target_entity_bundle' => 'article',
      'source_types' => ['log_source'],
    ])->save();

    WebhookSourceType::create([
      'id' => 'log_source',
      'label' => 'Log Source',
      'field_mappings' => [
        [
          'entity_field' => 'type',
          'json_path' => '$.bundle',
          'is_identifier' => FALSE,
        ],
        [
          'entity_field' => 'title',
          'json_path' => '$.name',
          'is_identifier' => FALSE,
        ],
      ],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    $this->logStore = new LogStore();
    $this->registerLogCapture();
  }

  /**
   * Tests that successful processing logs an info-level message.
   */
  public function testSuccessfulProcessingLogsInfoMessage(): void {
    $item = new WebhookQueueItem(
      endpointId: 'log_endpoint',
      sourceType: 'log_source',
      payload: ['bundle' => 'article', 'name' => 'Logged Node'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $this->container->get('entity_webhook.webhook_processor')->process($item);

    $infoMessages = $this->filterLogsByLevel(RfcLogLevel::INFO);
    $this->assertNotEmpty($infoMessages, 'Expected at least one info log entry for successful processing.');
  }

  /**
   * Tests that processing with an unknown endpoint logs a warning.
   */
  public function testUnknownEndpointLogsWarning(): void {
    $item = new WebhookQueueItem(
      endpointId: 'nonexistent_endpoint',
      sourceType: 'log_source',
      payload: ['name' => 'Test'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $this->container->get('entity_webhook.webhook_processor')->process($item);

    $warnings = $this->filterLogsByLevel(RfcLogLevel::WARNING);
    $this->assertNotEmpty($warnings, 'Expected a warning log for unknown endpoint.');
  }

  /**
   * Tests that a processing exception logs an error.
   */
  public function testProcessingExceptionLogsError(): void {
    WebhookSourceType::create([
      'id' => 'bad_source',
      'label' => 'Bad Source',
      'field_mappings' => [
        [
          'entity_field' => 'nonexistent_field_xyz',
          'json_path' => '$.name',
          'is_identifier' => FALSE,
        ],
      ],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookEndpoint::create([
      'id' => 'bad_endpoint',
      'label' => 'Bad Endpoint',
      'target_entity_type' => 'node',
      'source_types' => ['bad_source'],
    ])->save();

    $item = new WebhookQueueItem(
      endpointId: 'bad_endpoint',
      sourceType: 'bad_source',
      payload: ['name' => 'Test'],
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    $this->container->get('entity_webhook.webhook_processor')->process($item);

    $errors = $this->filterLogsByLevel(RfcLogLevel::ERROR);
    $this->assertNotEmpty($errors, 'Expected an error log for failed processing.');
  }

  /**
   * Registers a capturing logger for the entity_webhook channel.
   */
  private function registerLogCapture(): void {
    $store = $this->logStore;

    $capturingLogger = new class($store) implements LoggerInterface {
      public function __construct(private readonly LogStore $store) {}

      public function emergency(\Stringable|string $message, array $context = []): void {
        $this->store->add(RfcLogLevel::EMERGENCY, (string) $message);
      }

      public function alert(\Stringable|string $message, array $context = []): void {
        $this->store->add(RfcLogLevel::ALERT, (string) $message);
      }

      public function critical(\Stringable|string $message, array $context = []): void {
        $this->store->add(RfcLogLevel::CRITICAL, (string) $message);
      }

      public function error(\Stringable|string $message, array $context = []): void {
        $this->store->add(RfcLogLevel::ERROR, (string) $message);
      }

      public function warning(\Stringable|string $message, array $context = []): void {
        $this->store->add(RfcLogLevel::WARNING, (string) $message);
      }

      public function notice(\Stringable|string $message, array $context = []): void {
        $this->store->add(RfcLogLevel::NOTICE, (string) $message);
      }

      public function info(\Stringable|string $message, array $context = []): void {
        $this->store->add(RfcLogLevel::INFO, (string) $message);
      }

      public function debug(\Stringable|string $message, array $context = []): void {
        $this->store->add(RfcLogLevel::DEBUG, (string) $message);
      }

      public function log($level, \Stringable|string $message, array $context = []): void {
        $this->store->add($level, (string) $message);
      }
    };

    $this->container->get('logger.factory')->addLogger($capturingLogger);
  }

  /**
   * Filters captured log messages by RFC log level.
   *
   * @param int $level
   *   An RfcLogLevel constant.
   *
   * @return array<int, array{level: mixed, message: string}>
   *   All captured messages at the given level.
   */
  private function filterLogsByLevel(int $level): array {
    return array_values(array_filter(
      $this->logStore->entries,
      fn(array $entry) => $entry['level'] === $level,
    ));
  }

}

/**
 * Simple value store for captured log messages in tests.
 */
class LogStore {

  /**
   * All captured log entries.
   *
   * @var array<int, array{level: mixed, message: string}>
   */
  public array $entries = [];

  /**
   * Adds a log entry to the store.
   *
   * @param mixed $level
   *   The RFC log level.
   * @param string $message
   *   The log message.
   */
  public function add(mixed $level, string $message): void {
    $this->entries[] = ['level' => $level, 'message' => $message];
  }

}
