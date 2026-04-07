<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\entity_webhook\Service\WebhookProcessResult;

/**
 * Unit tests for WebhookProcessResult value object.
 *
 * @coversDefaultClass \Drupal\entity_webhook\Service\WebhookProcessResult
 * @group entity_webhook
 */
class WebhookProcessResultTest extends UnitTestCase {

  /**
   * Tests that success() sets all fields correctly for a 'created' operation.
   *
   * @covers ::success
   */
  public function testSuccessFactoryMethodSetsAllFieldsForCreatedOperation(): void {
    // Arrange
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn('42');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('Test Node');

    // Act
    $result = WebhookProcessResult::success('created', $entity);

    // Assert
    $this->assertTrue($result->success);
    $this->assertSame('created', $result->operation);
    $this->assertSame('42', $result->entityId);
    $this->assertSame('node', $result->entityTypeId);
    $this->assertSame('article', $result->bundle);
    $this->assertSame('Test Node', $result->label);
    $this->assertNull($result->error);
    $this->assertSame([], $result->errorDetails);
  }

  /**
   * Tests that success() sets the operation to 'updated' correctly.
   *
   * @covers ::success
   */
  public function testSuccessFactoryMethodSetsUpdatedOperation(): void {
    // Arrange
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn('5');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('page');
    $entity->method('label')->willReturn('About Page');

    // Act
    $result = WebhookProcessResult::success('updated', $entity);

    // Assert
    $this->assertTrue($result->success);
    $this->assertSame('updated', $result->operation);
  }

  /**
   * Tests that success() sets the operation to 'deleted' correctly.
   *
   * @covers ::success
   */
  public function testSuccessFactoryMethodSetsDeletedOperation(): void {
    // Arrange
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn('9');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('label')->willReturn('Old Article');

    // Act
    $result = WebhookProcessResult::success('deleted', $entity);

    // Assert
    $this->assertTrue($result->success);
    $this->assertSame('deleted', $result->operation);
  }

  /**
   * Tests that error() sets success=false, the error message, and null entity fields.
   *
   * @covers ::error
   */
  public function testErrorFactoryMethodSetsSuccessFalseAndErrorMessage(): void {
    // Arrange + Act
    $result = WebhookProcessResult::error('Endpoint not found.');

    // Assert
    $this->assertFalse($result->success);
    $this->assertNull($result->operation);
    $this->assertNull($result->entityId);
    $this->assertNull($result->entityTypeId);
    $this->assertNull($result->bundle);
    $this->assertNull($result->label);
    $this->assertSame('Endpoint not found.', $result->error);
    $this->assertSame([], $result->errorDetails);
  }

  /**
   * Tests that error() stores the optional details array.
   *
   * @covers ::error
   */
  public function testErrorFactoryMethodStoresDetailsArray(): void {
    // Arrange
    $details = ['source' => 'missing_source', 'endpoint' => 'my_endpoint'];

    // Act
    $result = WebhookProcessResult::error('Source type not found.', $details);

    // Assert
    $this->assertFalse($result->success);
    $this->assertSame('Source type not found.', $result->error);
    $this->assertSame($details, $result->errorDetails);
  }

  /**
   * Tests that toResponseArray() contains all required keys for a success result.
   *
   * @covers ::toResponseArray
   */
  public function testToResponseArrayOnSuccessContainsAllEntityFields(): void {
    // Arrange
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('id')->willReturn('7');
    $entity->method('getEntityTypeId')->willReturn('user');
    $entity->method('bundle')->willReturn('user');
    $entity->method('label')->willReturn('Alice');

    $result = WebhookProcessResult::success('updated', $entity);

    // Act
    $array = $result->toResponseArray();

    // Assert
    $this->assertSame('success', $array['status']);
    $this->assertSame('updated', $array['operation']);
    $this->assertSame('7', $array['entity_id']);
    $this->assertSame('user', $array['entity_type']);
    $this->assertSame('user', $array['bundle']);
    $this->assertSame('Alice', $array['label']);
  }

  /**
   * Tests that toResponseArray() contains status, error message, and details for error.
   *
   * @covers ::toResponseArray
   */
  public function testToResponseArrayOnErrorContainsStatusErrorAndDetails(): void {
    // Arrange
    $result = WebhookProcessResult::error('Source type not found.', ['source' => 'missing_source']);

    // Act
    $array = $result->toResponseArray();

    // Assert
    $this->assertSame('error', $array['status']);
    $this->assertSame('Source type not found.', $array['error']);
    $this->assertSame(['source' => 'missing_source'], $array['details']);
  }

}
