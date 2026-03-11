<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\entity_webhook\Form\WebhookEndpointForm;

/**
 * Unit tests for WebhookEndpointForm helper methods.
 *
 * @group entity_webhook
 */
class WebhookEndpointFormTest extends UnitTestCase {

  /**
   * Tests that getBundleOptions returns empty array when bundle info is empty.
   */
  public function testGetBundleOptionsReturnsEmptyArrayForEmptyEntityTypeId(): void {
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $bundleInfo->method('getBundleInfo')
      ->with('')
      ->willReturn([]);

    $form = new WebhookEndpointForm($bundleInfo);

    $result = $this->callProtectedMethod($form, 'getBundleOptions', ['']);

    $this->assertSame([], $result);
  }

  /**
   * Tests that getBundleOptions returns labelled options for a valid entity type.
   */
  public function testGetBundleOptionsReturnsLabelledOptionsForValidEntityType(): void {
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);
    $bundleInfo->method('getBundleInfo')
      ->with('node')
      ->willReturn([
        'article' => ['label' => 'Article'],
        'page' => ['label' => 'Basic page'],
      ]);

    $form = new WebhookEndpointForm($bundleInfo);

    $result = $this->callProtectedMethod($form, 'getBundleOptions', ['node']);

    $this->assertSame(['article' => 'Article', 'page' => 'Basic page'], $result);
  }

  /**
   * Tests that ajaxRebuildForm returns the entire form.
   */
  public function testAjaxRebuildFormReturnsEntireForm(): void {
    $bundleInfo = $this->createMock(EntityTypeBundleInfoInterface::class);

    $form = new WebhookEndpointForm($bundleInfo);

    $formArray = [
      '#prefix' => '<div id="webhook-endpoint-form">',
      '#suffix' => '</div>',
      'label' => ['#type' => 'textfield'],
      'target_entity_type' => ['#type' => 'select'],
      'bundle' => [
        '#type' => 'container',
        'target_entity_bundle' => ['#type' => 'select'],
      ],
    ];

    $result = $form->ajaxRebuildForm($formArray);

    $this->assertSame($formArray, $result);
  }

  /**
   * Calls a protected method on an object using reflection.
   *
   * @param object $object
   *   The object to call the method on.
   * @param string $method
   *   The method name.
   * @param array<mixed> $args
   *   The method arguments.
   *
   * @return mixed
   *   The return value of the method.
   */
  private function callProtectedMethod(object $object, string $method, array $args = []): mixed {
    $reflection = new \ReflectionMethod($object, $method);
    $reflection->setAccessible(TRUE);
    return $reflection->invokeArgs($object, $args);
  }

}
