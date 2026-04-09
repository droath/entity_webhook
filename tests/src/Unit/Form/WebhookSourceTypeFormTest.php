<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Unit\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\entity_webhook\Form\WebhookSourceTypeForm;
use Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorManagerInterface;
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
   * @param \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface|null $verificationManager
   *   Optional verification manager mock.
   * @param \Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorManagerInterface|null $payloadProcessorManager
   *   Optional payload processor manager mock.
   *
   * @return \Drupal\entity_webhook\Form\WebhookSourceTypeForm
   *   The form instance.
   */
  private function createForm(
    ?WebhookVerificationManagerInterface $verificationManager = NULL,
    ?WebhookPayloadProcessorManagerInterface $payloadProcessorManager = NULL,
  ): WebhookSourceTypeForm {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $verificationManager ??= $this->createMock(WebhookVerificationManagerInterface::class);
    $payloadProcessorManager ??= $this->createMock(WebhookPayloadProcessorManagerInterface::class);

    $form = new WebhookSourceTypeForm(
      $routeMatch,
      $verificationManager,
      $payloadProcessorManager,
    );

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnArgument(0);
    $form->setStringTranslation($translation);

    return $form;
  }

  /**
   * Tests that validateForm calls plugin configuration validation when plugin is selected.
   */
  public function testValidateFormDelegatesPluginValidationWhenPluginSelected(): void {
    $verificationManager = $this->createMock(WebhookVerificationManagerInterface::class);
    $verificationManager->expects($this->never())->method('createInstance');

    $form = $this->createForm($verificationManager);

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationPlugin')->willReturn('');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getUserInput')->willReturn([]);
    $formState->method('getValue')->willReturnMap([
      ['verification_plugin', NULL, ''],
    ]);

    $formArray = [];
    $form->validateForm($formArray, $formState);
  }

  /**
   * Tests that resolveSelectedVerificationPlugin returns entity value when no user input.
   */
  public function testResolveSelectedVerificationPluginReturnsEntityValueWhenNoUserInput(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationPlugin')->willReturn('hmac_verification');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getUserInput')->willReturn([]);
    $formState->method('getValue')->willReturn(NULL);

    $result = $this->callProtectedMethod($form, 'resolveSelectedVerificationPlugin', [$formState]);

    $this->assertSame('hmac_verification', $result);
  }

  /**
   * Tests that resolveSelectedVerificationPlugin prefers user input during AJAX rebuilds.
   */
  public function testResolveSelectedVerificationPluginPrefersUserInputDuringAjaxRebuild(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationPlugin')->willReturn('hmac_verification');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getUserInput')->willReturn(['verification_plugin' => 'api_key_verification']);
    $formState->method('getValue')->willReturn(NULL);

    $result = $this->callProtectedMethod($form, 'resolveSelectedVerificationPlugin', [$formState]);

    $this->assertSame('api_key_verification', $result);
  }

  /**
   * Tests that resolveVerificationConfig returns form state value when non-empty.
   */
  public function testResolveVerificationConfigReturnsFormStateValueWhenNonEmpty(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationConfig')->willReturn(['secret' => 'entity_secret']);
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      [['verification_config'], NULL, ['secret' => 'form_secret']],
    ]);

    $result = $this->callProtectedMethod($form, 'resolveVerificationConfig', [$formState]);

    $this->assertSame(['secret' => 'form_secret'], $result);
  }

  /**
   * Tests that resolveVerificationConfig falls back to entity config when form value is empty.
   */
  public function testResolveVerificationConfigFallsBackToEntityConfigWhenFormEmpty(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getVerificationConfig')->willReturn(['secret' => 'entity_secret']);
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      [['verification_config'], NULL, []],
    ]);

    $result = $this->callProtectedMethod($form, 'resolveVerificationConfig', [$formState]);

    $this->assertSame(['secret' => 'entity_secret'], $result);
  }

  /**
   * Tests that resolveSelectedPayloadProcessor returns entity value when no user input.
   */
  public function testResolveSelectedPayloadProcessorReturnsEntityValueWhenNoUserInput(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getPayloadProcessor')->willReturn('array_iterator');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getUserInput')->willReturn([]);
    $formState->method('getValue')->willReturn(NULL);

    $result = $this->callProtectedMethod($form, 'resolveSelectedPayloadProcessor', [$formState]);

    $this->assertSame('array_iterator', $result);
  }

  /**
   * Tests that resolveSelectedPayloadProcessor prefers user input during AJAX rebuilds.
   */
  public function testResolveSelectedPayloadProcessorPrefersUserInputDuringAjaxRebuild(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getPayloadProcessor')->willReturn('');
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getUserInput')->willReturn(['payload_processor' => 'array_iterator']);
    $formState->method('getValue')->willReturn(NULL);

    $result = $this->callProtectedMethod($form, 'resolveSelectedPayloadProcessor', [$formState]);

    $this->assertSame('array_iterator', $result);
  }

  /**
   * Tests that resolvePayloadProcessorConfig returns form state value when non-empty.
   */
  public function testResolvePayloadProcessorConfigReturnsFormStateValueWhenNonEmpty(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getPayloadProcessorConfig')->willReturn(['path' => '$.entity_path']);
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      [['payload_processor_config'], NULL, ['path' => '$.form_path']],
    ]);

    $result = $this->callProtectedMethod($form, 'resolvePayloadProcessorConfig', [$formState]);

    $this->assertSame(['path' => '$.form_path'], $result);
  }

  /**
   * Tests that resolvePayloadProcessorConfig falls back to entity config when form value is empty.
   */
  public function testResolvePayloadProcessorConfigFallsBackToEntityConfigWhenFormEmpty(): void {
    $form = $this->createForm();

    $entity = $this->createMock(WebhookSourceTypeInterface::class);
    $entity->method('getPayloadProcessorConfig')->willReturn(['path' => '$.entity_path']);
    $form->setEntity($entity);

    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      [['payload_processor_config'], NULL, []],
    ]);

    $result = $this->callProtectedMethod($form, 'resolvePayloadProcessorConfig', [$formState]);

    $this->assertSame(['path' => '$.entity_path'], $result);
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
