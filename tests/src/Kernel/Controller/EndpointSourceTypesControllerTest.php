<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Controller;

use Drupal\entity_webhook\Controller\EndpointSourceTypesController;
use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for EndpointSourceTypesController.
 *
 * @group entity_webhook
 */
class EndpointSourceTypesControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);

    WebhookSourceType::create([
      'id' => 'shopify',
      'label' => 'Shopify',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookEndpoint::create([
      'id' => 'my_endpoint',
      'label' => 'My Endpoint',
      'target_entity_type' => 'user',
      'source_types' => ['shopify'],
    ])->save();
  }

  /**
   * Tests that listSourceTypes render array contains source type data.
   */
  public function testListSourceTypesRenderArrayContainsSourceTypeRows(): void {
    $endpoint = WebhookEndpoint::load('my_endpoint');

    /** @var \Drupal\entity_webhook\Controller\EndpointSourceTypesController $controller */
    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EndpointSourceTypesController::class);

    $build = $controller->listSourceTypes($endpoint);

    $this->assertSame('table', $build['#type']);
    $this->assertCount(1, $build['#rows']);
    $this->assertSame('Shopify', $build['#rows'][0]['label']);
    $this->assertSame('shopify', $build['#rows'][0]['id']);
  }

  /**
   * Tests that listSourceTypes returns empty table when endpoint has no source types.
   */
  public function testListSourceTypesReturnsEmptyTableWhenNoSourceTypes(): void {
    WebhookEndpoint::create([
      'id' => 'empty_endpoint',
      'label' => 'Empty Endpoint',
      'target_entity_type' => 'user',
      'source_types' => [],
    ])->save();

    $endpoint = WebhookEndpoint::load('empty_endpoint');

    /** @var \Drupal\entity_webhook\Controller\EndpointSourceTypesController $controller */
    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EndpointSourceTypesController::class);

    $build = $controller->listSourceTypes($endpoint);

    $this->assertSame('table', $build['#type']);
    $this->assertCount(0, $build['#rows']);
  }

  /**
   * Tests that getTitle returns a string containing the endpoint label.
   */
  public function testGetTitleContainsEndpointLabel(): void {
    $endpoint = WebhookEndpoint::load('my_endpoint');

    /** @var \Drupal\entity_webhook\Controller\EndpointSourceTypesController $controller */
    $controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EndpointSourceTypesController::class);

    $title = $controller->getTitle($endpoint);

    $this->assertStringContainsString('My Endpoint', $title);
  }

}
