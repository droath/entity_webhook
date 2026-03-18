<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\entity_webhook\Form\WebhookSourceTypeForm;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface;

/**
 * Unit tests for WebhookSourceTypeForm orchestration methods.
 *
 * @group entity_webhook
 */
class WebhookSourceTypeFormTest extends UnitTestCase {

  /**
   * Creates a WebhookSourceTypeForm instance with mocked dependencies.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface|null $entityFieldManager
   *   Optional entity field manager mock.
   * @param \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface|null $verificationManager
   *   Optional verification manager mock.
   * @param \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface|null $mutationManager
   *   Optional mutation manager mock.
   *
   * @return \Drupal\entity_webhook\Form\WebhookSourceTypeForm
   *   The form instance.
   */
  private function createForm(
    ?EntityFieldManagerInterface $entityFieldManager = NULL,
    ?WebhookVerificationManagerInterface $verificationManager = NULL,
    ?FieldValueMutationManagerInterface $mutationManager = NULL,
  ): WebhookSourceTypeForm {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $entityFieldManager ??= $this->createMock(EntityFieldManagerInterface::class);
    $verificationManager ??= $this->createMock(WebhookVerificationManagerInterface::class);
    $mutationManager ??= $this->createMock(FieldValueMutationManagerInterface::class);

    $form = new WebhookSourceTypeForm(
      $routeMatch,
      $entityFieldManager,
      $verificationManager,
      $mutationManager,
    );

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnArgument(0);
    $form->setStringTranslation($translation);

    return $form;
  }

  /**
   * Tests isAjaxMappingOperation returns true for add_mapping trigger name.
   */
  public function testIsAjaxMappingOperationReturnsTrueForAddMappingTrigger(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'add_mapping']);

    $result = $this->callProtectedMethod($form, 'isAjaxMappingOperation', [$formState]);

    $this->assertTrue($result);
  }

  /**
   * Tests isAjaxMappingOperation returns true for remove_mapping_N trigger names.
   */
  public function testIsAjaxMappingOperationReturnsTrueForRemoveMappingTrigger(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'remove_mapping_2']);

    $result = $this->callProtectedMethod($form, 'isAjaxMappingOperation', [$formState]);

    $this->assertTrue($result);
  }

  /**
   * Tests isAjaxMappingOperation returns false for standard form submit triggers.
   */
  public function testIsAjaxMappingOperationReturnsFalseForStandardSubmit(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'op']);

    $result = $this->callProtectedMethod($form, 'isAjaxMappingOperation', [$formState]);

    $this->assertFalse($result);
  }

  /**
   * Tests isAjaxMappingOperation returns false when triggering element has no name.
   */
  public function testIsAjaxMappingOperationReturnsFalseWhenNoTriggeringElementName(): void {
    $form = $this->createForm();

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn([]);

    $result = $this->callProtectedMethod($form, 'isAjaxMappingOperation', [$formState]);

    $this->assertFalse($result);
  }

  /**
   * Tests that validateForm skips identifier check for AJAX add mapping triggers.
   */
  public function testValidateFormSkipsIdentifierCheckForAjaxAddMapping(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationPlugin')->willReturn('');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'add_mapping']);
    $formState->expects($this->never())->method('setErrorByName');

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  /**
   * Tests that validateForm skips identifier check for AJAX remove mapping triggers.
   */
  public function testValidateFormSkipsIdentifierCheckForAjaxRemoveMapping(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationPlugin')->willReturn('');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'remove_mapping_0']);
    $formState->expects($this->never())->method('setErrorByName');

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  /**
   * Tests that validateForm sets an error when mappings exist but none are identifiers.
   */
  public function testValidateFormSetsErrorWhenMappingsExistButNoneAreIdentifiers(): void {
    $nonEmptyMappings = [
      0 => ['entity_field' => 'title', 'json_path' => '$.name', 'is_identifier' => '0'],
    ];

    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationPlugin')->willReturn('');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'op']);
    $formState->method('getUserInput')->willReturn([]);
    $formState->method('getValue')->willReturnMap([
      ['field_mappings', NULL, $nonEmptyMappings],
      ['verification_plugin', NULL, ''],
    ]);
    $formState->expects($this->once())
      ->method('setErrorByName')
      ->with('field_mappings', $this->anything());

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  /**
   * Tests that validateForm does not set error when at least one identifier is present.
   */
  public function testValidateFormDoesNotSetErrorWhenIdentifierPresent(): void {
    $nonEmptyMappings = [
      0 => ['entity_field' => 'title', 'json_path' => '$.name', 'is_identifier' => '1'],
    ];

    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationPlugin')->willReturn('');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'op']);
    $formState->method('getUserInput')->willReturn([]);
    $formState->method('getValue')->willReturnMap([
      ['field_mappings', NULL, $nonEmptyMappings],
      ['verification_plugin', NULL, ''],
    ]);
    $formState->expects($this->never())->method('setErrorByName');

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  /**
   * Tests that validateForm does not set identifier error when no non-empty mappings exist.
   */
  public function testValidateFormSkipsIdentifierCheckWhenNoNonEmptyMappings(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationPlugin')->willReturn('');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'op']);
    $formState->method('getUserInput')->willReturn([]);
    $formState->method('getValue')->willReturnMap([
      ['field_mappings', NULL, [
        0 => ['entity_field' => '', 'json_path' => '', 'is_identifier' => '0'],
      ]],
      ['verification_plugin', NULL, ''],
    ]);
    $formState->expects($this->never())->method('setErrorByName');

    $formArray = [];
    $form->validateForm($formArray, $formState);
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
