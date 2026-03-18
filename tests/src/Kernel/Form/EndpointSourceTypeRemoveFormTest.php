<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\entity_webhook\Form\EndpointSourceTypeRemoveForm;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for EndpointSourceTypeRemoveForm.
 *
 * @group entity_webhook
 */
class EndpointSourceTypeRemoveFormTest extends KernelTestBase {

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

    WebhookSourceType::create([
      'id' => 'woocommerce',
      'label' => 'WooCommerce',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    WebhookEndpoint::create([
      'id' => 'my_endpoint',
      'label' => 'My Endpoint',
      'target_entity_type' => 'user',
      'source_types' => ['shopify', 'woocommerce'],
    ])->save();
  }

  /**
   * Returns a fully-constructed form instance.
   */
  private function getForm(): EndpointSourceTypeRemoveForm {
    /** @var \Drupal\entity_webhook\Form\EndpointSourceTypeRemoveForm $form */
    $form = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(EndpointSourceTypeRemoveForm::class);

    return $form;
  }

  /**
   * Tests that submitForm removes the source type and persists the endpoint.
   */
  public function testSubmitFormRemovesSourceTypeAndPersistsEndpoint(): void {
    $endpoint = WebhookEndpoint::load('my_endpoint');
    $this->assertTrue($endpoint->hasSourceType('shopify'));

    $form = $this->getForm();
    $form_state = new FormState();
    $form_array = [];

    $form->buildForm($form_array, $form_state, $endpoint, 'shopify');
    $form->submitForm($form_array, $form_state);

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $reloaded */
    $reloaded = WebhookEndpoint::load('my_endpoint');

    $this->assertFalse($reloaded->hasSourceType('shopify'));
    $this->assertTrue($reloaded->hasSourceType('woocommerce'));
  }

  /**
   * Tests that submitForm sets a status message with source type and endpoint labels.
   */
  public function testSubmitFormSetsStatusMessageWithSourceTypeAndEndpointLabels(): void {
    $endpoint = WebhookEndpoint::load('my_endpoint');

    $form = $this->getForm();
    $form_state = new FormState();
    $form_array = [];

    $form->buildForm($form_array, $form_state, $endpoint, 'shopify');
    $form->submitForm($form_array, $form_state);

    $messages = $this->container->get('messenger')->messagesByType('status');
    $messageText = (string) reset($messages);

    $this->assertStringContainsString('Shopify', $messageText);
    $this->assertStringContainsString('My Endpoint', $messageText);
  }

  /**
   * Tests that submitForm redirects to the source types listing route.
   */
  public function testSubmitFormRedirectsToSourceTypesListing(): void {
    $endpoint = WebhookEndpoint::load('my_endpoint');

    $form = $this->getForm();
    $form_state = new FormState();
    $form_array = [];

    $form->buildForm($form_array, $form_state, $endpoint, 'shopify');
    $form->submitForm($form_array, $form_state);

    $redirect = $form_state->getRedirect();
    $this->assertNotNull($redirect);
    $this->assertStringContainsString('my_endpoint', (string) $redirect->toString());
  }

  /**
   * Tests that submitForm with a NULL endpoint is a no-op.
   */
  public function testSubmitFormWithNullEndpointDoesNothing(): void {
    $form = $this->getForm();
    $form_state = new FormState();
    $form_array = [];

    // buildForm with NULL endpoint leaves $this->endpoint as NULL.
    $form->buildForm($form_array, $form_state, NULL, 'shopify');
    $form->submitForm($form_array, $form_state);

    // Endpoint should be unchanged.
    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $reloaded */
    $reloaded = WebhookEndpoint::load('my_endpoint');
    $this->assertCount(2, $reloaded->getSourceTypeIds());
  }

  /**
   * Tests that getQuestion contains both the source type and endpoint labels.
   */
  public function testGetQuestionContainsSourceTypeAndEndpointLabels(): void {
    $endpoint = WebhookEndpoint::load('my_endpoint');

    $form = $this->getForm();
    $form_state = new FormState();

    $form->buildForm([], $form_state, $endpoint, 'shopify');

    $question = (string) $form->getQuestion();

    $this->assertStringContainsString('Shopify', $question);
    $this->assertStringContainsString('My Endpoint', $question);
  }

}
