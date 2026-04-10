<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Plugin\WebhookPayloadProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\ArrayIteratorProcessor;
use Drupal\entity_webhook\Service\JsonPathExtractorInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the ArrayIteratorProcessor plugin.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\ArrayIteratorProcessor
 * @group entity_webhook
 */
class ArrayIteratorProcessorTest extends UnitTestCase {

  /**
   * The JSONPath extractor mock.
   */
  private MockObject&JsonPathExtractorInterface $jsonPathExtractor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->jsonPathExtractor = $this->createMock(JsonPathExtractorInterface::class);
  }

  /**
   * Builds an ArrayIteratorProcessor with the given configuration.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration including 'path' and optionally 'merge_from_root'.
   *
   * @return \Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\ArrayIteratorProcessor
   *   The plugin instance under test.
   */
  private function buildPlugin(array $configuration = []): ArrayIteratorProcessor {
    return new ArrayIteratorProcessor(
      $configuration,
      'array_iterator',
      ['id' => 'array_iterator', 'label' => 'Array Iterator'],
      $this->jsonPathExtractor,
    );
  }

  /**
   * Tests that a payload array is split into one result per item.
   *
   * @covers ::process
   */
  public function testProcessSplitsArrayIntoOnePayloadPerItem(): void {
    // Arrange.
    $addresses = [['id' => 1], ['id' => 2]];
    $payload = ['addresses' => $addresses, 'email' => 'test@example.com'];

    $this->jsonPathExtractor
      ->method('extract')
      ->with($payload, '$.addresses')
      ->willReturn($addresses);

    $plugin = $this->buildPlugin(['path' => '$.addresses']);

    // Act.
    $result = $plugin->process($payload, []);

    // Assert.
    $this->assertCount(2, $result);
    $this->assertSame(['id' => 1], $result[0]);
    $this->assertSame(['id' => 2], $result[1]);
  }

  /**
   * Tests that root-level fields are merged into each sub-payload.
   *
   * @covers ::process
   */
  public function testProcessMergesRootFieldsIntoEachSubPayload(): void {
    // Arrange.
    $addresses = [['id' => 1], ['id' => 2]];
    $payload = [
      'email' => 'customer@example.com',
      'addresses' => $addresses,
    ];

    $this->jsonPathExtractor
      ->method('extract')
      ->willReturn($addresses);

    $plugin = $this->buildPlugin([
      'path' => '$.addresses',
      'merge_from_root' => ['email'],
    ]);

    // Act.
    $result = $plugin->process($payload, []);

    // Assert.
    $this->assertCount(2, $result);
    $this->assertSame('customer@example.com', $result[0]['email']);
    $this->assertSame('customer@example.com', $result[1]['email']);
  }

  /**
   * Tests that an empty array at the configured path returns no payloads.
   *
   * @covers ::process
   */
  public function testProcessReturnsEmptyArrayWhenPathContainsEmptyArray(): void {
    // Arrange.
    $payload = ['addresses' => []];

    $this->jsonPathExtractor
      ->method('extract')
      ->willReturn([]);

    $plugin = $this->buildPlugin(['path' => '$.addresses']);

    // Act.
    $result = $plugin->process($payload, []);

    // Assert.
    $this->assertSame([], $result);
  }

  /**
   * Tests that a missing path returns an empty array.
   *
   * @covers ::process
   */
  public function testProcessReturnsEmptyArrayWhenPathDoesNotExistInPayload(): void {
    // Arrange.
    $payload = ['email' => 'test@example.com'];

    $this->jsonPathExtractor
      ->method('extract')
      ->willReturn(NULL);

    $plugin = $this->buildPlugin(['path' => '$.addresses']);

    // Act.
    $result = $plugin->process($payload, []);

    // Assert.
    $this->assertSame([], $result);
  }

  /**
   * Tests that an empty path configuration returns an empty array.
   *
   * @covers ::process
   */
  public function testProcessReturnsEmptyArrayWhenPathConfigurationIsEmpty(): void {
    // Arrange.
    $payload = ['addresses' => [['id' => 1]]];

    // The extractor should never be called when path is empty.
    $this->jsonPathExtractor->expects($this->never())->method('extract');

    $plugin = $this->buildPlugin(['path' => '']);

    // Act.
    $result = $plugin->process($payload, []);

    // Assert.
    $this->assertSame([], $result);
  }

  /**
   * Tests that missing merge_from_root fields are skipped gracefully.
   *
   * @covers ::process
   */
  public function testProcessSkipsMissingRootFieldsGracefully(): void {
    // Arrange.
    $addresses = [['id' => 1]];
    // 'email' is not present in the payload root.
    $payload = ['addresses' => $addresses];

    $this->jsonPathExtractor
      ->method('extract')
      ->willReturn($addresses);

    $plugin = $this->buildPlugin([
      'path' => '$.addresses',
      'merge_from_root' => ['email'],
    ]);

    // Act.
    $result = $plugin->process($payload, []);

    // Assert: item is returned without the missing 'email' key.
    $this->assertCount(1, $result);
    $this->assertArrayNotHasKey('email', $result[0]);
    $this->assertSame(['id' => 1], $result[0]);
  }

  /**
   * Tests that item-level fields take precedence over root-level merge fields.
   *
   * When a sub-item already has a key that is also listed in merge_from_root,
   * the sub-item value is preserved and the root value is not applied.
   *
   * @covers ::process
   */
  public function testProcessPreservesExistingSubItemFieldsOverRootMergeFields(): void {
    // Arrange: both sub-item and root carry 'email'.
    $addresses = [['id' => 1, 'email' => 'item@example.com']];
    $payload = [
      'email' => 'root@example.com',
      'addresses' => $addresses,
    ];

    $this->jsonPathExtractor
      ->method('extract')
      ->willReturn($addresses);

    $plugin = $this->buildPlugin([
      'path' => '$.addresses',
      'merge_from_root' => ['email'],
    ]);

    // Act.
    $result = $plugin->process($payload, []);

    // Assert: the sub-item's own value wins.
    $this->assertCount(1, $result);
    $this->assertSame('item@example.com', $result[0]['email']);
  }

  /**
   * Tests that a deeply nested JSONPath expression extracts items correctly.
   *
   * @covers ::process
   */
  public function testProcessExtractsItemsFromDeepNestedPath(): void {
    // Arrange.
    $nestedAddresses = [['id' => 10], ['id' => 20]];
    $payload = [
      'customer' => [
        'addresses' => $nestedAddresses,
      ],
    ];

    $this->jsonPathExtractor
      ->method('extract')
      ->with($payload, '$.customer.addresses')
      ->willReturn($nestedAddresses);

    $plugin = $this->buildPlugin(['path' => '$.customer.addresses']);

    // Act.
    $result = $plugin->process($payload, []);

    // Assert.
    $this->assertCount(2, $result);
    $this->assertSame(10, $result[0]['id']);
    $this->assertSame(20, $result[1]['id']);
  }

  /**
   * Tests that defaultConfiguration provides empty path and merge fields.
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfigurationProvidesExpectedDefaults(): void {
    // Arrange.
    $plugin = $this->buildPlugin();

    // Act.
    $config = $plugin->defaultConfiguration();

    // Assert.
    $this->assertSame('', $config['path']);
    $this->assertSame([], $config['merge_from_root']);
  }

  /**
   * Tests that buildConfigurationForm returns path and merge_from_root fields.
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationFormReturnsExpectedFields(): void {
    // Arrange.
    $plugin = $this->buildPlugin([
      'path' => '$.addresses',
      'merge_from_root' => ['email', 'customer_id'],
    ]);
    $plugin->setStringTranslation($this->getStringTranslationStub());
    $formState = $this->createMock(FormStateInterface::class);

    // Act.
    $form = $plugin->buildConfigurationForm([], $formState);

    // Assert.
    $this->assertArrayHasKey('path', $form);
    $this->assertSame('textfield', $form['path']['#type']);
    $this->assertSame('$.addresses', $form['path']['#default_value']);
    $this->assertArrayHasKey('merge_from_root', $form);
    $this->assertSame('textarea', $form['merge_from_root']['#type']);
    $this->assertSame("email\ncustomer_id", $form['merge_from_root']['#default_value']);
  }

  /**
   * Tests that submitConfigurationForm stores parsed path and merge_from_root.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationFormStoresPathAndMergeFromRoot(): void {
    // Arrange.
    $plugin = $this->buildPlugin();
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      ['path', NULL, '$.addresses'],
      ['merge_from_root', NULL, "email\ncustomer_id\n"],
    ]);

    // Act.
    $form = [];
    $plugin->submitConfigurationForm($form, $formState);

    // Assert.
    $config = $plugin->getConfiguration();
    $this->assertSame('$.addresses', $config['path']);
    $this->assertSame(['email', 'customer_id'], $config['merge_from_root']);
  }

  /**
   * Tests that validateConfigurationForm sets an error when path is empty.
   *
   * @covers ::validateConfigurationForm
   */
  public function testValidateConfigurationFormSetsErrorWhenPathIsEmpty(): void {
    // Arrange.
    $plugin = $this->buildPlugin();
    $plugin->setStringTranslation($this->getStringTranslationStub());
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')
      ->with('path')
      ->willReturn('');
    $formState->expects($this->once())
      ->method('setErrorByName')
      ->with('path', $this->anything());

    // Act.
    $form = [];
    $plugin->validateConfigurationForm($form, $formState);
  }

  /**
   * Tests that validateConfigurationForm sets no error when path is non-empty.
   *
   * @covers ::validateConfigurationForm
   */
  public function testValidateConfigurationFormSetsNoErrorWhenPathIsProvided(): void {
    // Arrange.
    $plugin = $this->buildPlugin();
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')
      ->with('path')
      ->willReturn('$.addresses');
    $formState->expects($this->never())->method('setErrorByName');

    // Act.
    $form = [];
    $plugin->validateConfigurationForm($form, $formState);
  }

  /**
   * Tests that submitConfigurationForm trims whitespace and filters empty lines.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationFormTrimsAndFiltersEmptyLines(): void {
    // Arrange.
    $plugin = $this->buildPlugin();
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      ['path', NULL, '  $.items  '],
      ['merge_from_root', NULL, "  email  \n\n  customer_id  \n"],
    ]);

    // Act.
    $form = [];
    $plugin->submitConfigurationForm($form, $formState);

    // Assert.
    $config = $plugin->getConfiguration();
    $this->assertSame('$.items', $config['path']);
    $this->assertSame(['email', 'customer_id'], $config['merge_from_root']);
  }

}
