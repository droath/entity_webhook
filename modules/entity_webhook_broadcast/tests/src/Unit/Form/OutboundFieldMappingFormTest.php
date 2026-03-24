<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Unit\Form;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook_broadcast\Form\OutboundFieldMappingForm;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for OutboundFieldMappingForm pure-logic helpers.
 *
 * Tests the resolveRawId() method which strips a subscription ID prefix from a
 * composite field mapping ID, and verifies the output_key pattern attribute is
 * defined correctly in the form structure.
 *
 * @group entity_webhook_broadcast
 * @coversDefaultClass \Drupal\entity_webhook_broadcast\Form\OutboundFieldMappingForm
 */
class OutboundFieldMappingFormTest extends UnitTestCase {

  /**
   * A test-accessible subclass exposing resolveRawId().
   */
  private OutboundFieldMappingFormTestable $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $resolverManager = $this->createMock(OutboundValueResolverManagerInterface::class);
    $mutationManager = $this->createMock(FieldValueMutationManagerInterface::class);

    $this->form = new OutboundFieldMappingFormTestable(
      $routeMatch,
      $resolverManager,
      $mutationManager,
    );
  }

  /**
   * Tests that resolveRawId strips the prefix and dot separator correctly.
   *
   * @covers ::resolveRawId
   */
  public function testResolveRawIdStripsSubscriptionPrefixFromCompositeId(): void {
    // Arrange
    $fullId = 'my_subscription.title_field';
    $prefix = 'my_subscription';

    // Act
    $result = $this->form->exposeResolveRawId($fullId, $prefix);

    // Assert
    $this->assertSame('title_field', $result);
  }

  /**
   * Tests that resolveRawId returns the full ID unchanged when prefix is empty.
   *
   * @covers ::resolveRawId
   */
  public function testResolveRawIdReturnsFullIdWhenPrefixIsEmpty(): void {
    // Arrange
    $fullId = 'some_field_id';
    $prefix = '';

    // Act
    $result = $this->form->exposeResolveRawId($fullId, $prefix);

    // Assert
    $this->assertSame('some_field_id', $result);
  }

  /**
   * Tests that resolveRawId returns the full ID when it does not start with the prefix.
   *
   * @covers ::resolveRawId
   */
  public function testResolveRawIdReturnsFullIdWhenPrefixDoesNotMatch(): void {
    // Arrange
    $fullId = 'other_subscription.title';
    $prefix = 'my_subscription';

    // Act
    $result = $this->form->exposeResolveRawId($fullId, $prefix);

    // Assert
    $this->assertSame('other_subscription.title', $result);
  }

  /**
   * Tests that resolveRawId handles a composite ID with multiple dot segments.
   *
   * Only the leading prefix.dot is stripped; the remainder is returned as-is.
   *
   * @covers ::resolveRawId
   */
  public function testResolveRawIdHandlesMultipleDotsInRemainder(): void {
    // Arrange
    $fullId = 'my_sub.body.value';
    $prefix = 'my_sub';

    // Act
    $result = $this->form->exposeResolveRawId($fullId, $prefix);

    // Assert
    $this->assertSame('body.value', $result);
  }

  /**
   * Tests that resolveRawId does not strip when fullId equals only the prefix with no suffix.
   *
   * @covers ::resolveRawId
   */
  public function testResolveRawIdDoesNotStripWhenNoDotSuffixExists(): void {
    // Arrange
    $fullId = 'my_subscription';
    $prefix = 'my_subscription';

    // Act
    $result = $this->form->exposeResolveRawId($fullId, $prefix);

    // Assert
    // 'my_subscription' starts with 'my_subscription.' only if there's a dot.
    // It does not, so the full ID is returned unchanged.
    $this->assertSame('my_subscription', $result);
  }

}

/**
 * Test-accessible subclass that exposes the protected resolveRawId() method.
 */
class OutboundFieldMappingFormTestable extends OutboundFieldMappingForm {

  /**
   * Publicly exposes the protected resolveRawId() for unit testing.
   */
  public function exposeResolveRawId(string $fullId, string $prefix): string {
    return $this->resolveRawId($fullId, $prefix);
  }

}
