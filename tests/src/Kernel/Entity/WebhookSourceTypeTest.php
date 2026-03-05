<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Entity;

use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for WebhookSourceType config entity CRUD operations.
 *
 * @group entity_webhook
 */
class WebhookSourceTypeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook'];

  /**
   * Tests that a WebhookSourceType can be created and loaded.
   */
  public function testCreateAndLoadWebhookSourceType(): void {
    WebhookSourceType::create([
      'id' => 'shopify_order',
      'label' => 'Shopify Order',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    $loaded = WebhookSourceType::load('shopify_order');

    $this->assertInstanceOf(WebhookSourceTypeInterface::class, $loaded);
    $this->assertSame('shopify_order', $loaded->id());
    $this->assertSame('Shopify Order', $loaded->label());
  }

  /**
   * Tests that field mappings are stored and retrieved correctly.
   */
  public function testFieldMappingsStoredAndRetrieved(): void {
    WebhookSourceType::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'field_mappings' => [
        [
          'entity_field' => 'field_external_id',
          'json_path' => '$.id',
          'is_identifier' => TRUE,
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

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('test_source');
    $mappings = $loaded->getFieldMappings();

    $this->assertCount(2, $mappings);
    $this->assertSame('field_external_id', $mappings[0]->entityField);
    $this->assertTrue($mappings[0]->isIdentifier);
    $this->assertSame('title', $mappings[1]->entityField);
    $this->assertFalse($mappings[1]->isIdentifier);
  }

  /**
   * Tests that getIdentifierMappings returns only identifier-flagged mappings.
   */
  public function testGetIdentifierMappingsReturnsOnlyIdentifiers(): void {
    WebhookSourceType::create([
      'id' => 'source_with_identifiers',
      'label' => 'Source With Identifiers',
      'field_mappings' => [
        [
          'entity_field' => 'field_external_id',
          'json_path' => '$.id',
          'is_identifier' => TRUE,
        ],
        [
          'entity_field' => 'title',
          'json_path' => '$.name',
          'is_identifier' => FALSE,
        ],
        [
          'entity_field' => 'field_sku',
          'json_path' => '$.sku',
          'is_identifier' => TRUE,
        ],
      ],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('source_with_identifiers');
    $identifiers = $loaded->getIdentifierMappings();

    $this->assertCount(2, $identifiers);
    $this->assertSame('field_external_id', $identifiers[0]->entityField);
    $this->assertSame('field_sku', $identifiers[1]->entityField);
  }

  /**
   * Tests that a WebhookSourceType can be updated.
   */
  public function testUpdateWebhookSourceType(): void {
    WebhookSourceType::create([
      'id' => 'updatable_source',
      'label' => 'Original Label',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $loaded */
    $loaded = WebhookSourceType::load('updatable_source');
    $loaded->set('label', 'Updated Label');
    $loaded->set('verification_plugin', 'hmac');
    $loaded->save();

    $reloaded = WebhookSourceType::load('updatable_source');
    $this->assertSame('Updated Label', $reloaded->label());
    $this->assertSame('hmac', $reloaded->getVerificationPlugin());
  }

  /**
   * Tests that a WebhookSourceType can be deleted.
   */
  public function testDeleteWebhookSourceType(): void {
    WebhookSourceType::create([
      'id' => 'deletable_source',
      'label' => 'Deletable Source',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    $loaded = WebhookSourceType::load('deletable_source');
    $this->assertNotNull($loaded);

    $loaded->delete();

    $this->assertNull(WebhookSourceType::load('deletable_source'));
  }

}
