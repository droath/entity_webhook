<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Form;

use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Form\FormState;
use Drupal\entity_webhook\Entity\WebhookEndpoint;
use Drupal\entity_webhook\Entity\WebhookSourceType;
use Drupal\entity_webhook\Form\WebhookSourceTypeForm;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for WebhookSourceTypeForm build, validation, and save cycles.
 *
 * The form now handles only label, machine name, and verification plugin
 * configuration. Field mappings are managed via WebhookFieldMappingForm.
 *
 * @group entity_webhook
 */
class WebhookSourceTypeFormTest extends KernelTestBase {

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

    WebhookEndpoint::create([
      'id' => 'node_endpoint',
      'label' => 'Node Endpoint',
      'target_entity_type' => 'user',
      'target_entity_bundle' => '',
      'source_types' => [],
    ])->save();
  }

  /**
   * Returns a fully-initialized form instance ready for buildForm() calls.
   *
   * EntityForm requires moduleHandler and entityTypeManager to be injected
   * before buildForm() is called. The class_resolver normally handles this
   * via the FormBuilder, so we set them manually here.
   */
  private function getForm(): WebhookSourceTypeForm {
    /** @var \Drupal\entity_webhook\Form\WebhookSourceTypeForm $form */
    $form = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(WebhookSourceTypeForm::class);

    $form->setModuleHandler($this->container->get('module_handler'));
    $form->setEntityTypeManager($this->container->get('entity_type.manager'));

    return $form;
  }

  /**
   * Tests that the form builds without fatal errors for a new source type entity.
   */
  public function testFormBuildsForNewSourceTypeEntity(): void {
    $entity = WebhookSourceType::create([
      'id' => '',
      'label' => '',
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    $this->assertArrayHasKey('label', $built);
    $this->assertArrayHasKey('id', $built);
    $this->assertArrayHasKey('verification', $built);
    $this->assertArrayNotHasKey('field_mappings', $built);
  }

  /**
   * Tests that the verification_plugin dropdown is present in the built form.
   */
  public function testFormContainsVerificationPluginDropdown(): void {
    $entity = WebhookSourceType::create([
      'id' => '',
      'label' => '',
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    $this->assertArrayHasKey('verification_plugin', $built['verification']);
    $this->assertSame('select', $built['verification']['verification_plugin']['#type']);
  }

  /**
   * Tests that the verification_plugin dropdown options include the three bundled plugins.
   */
  public function testVerificationPluginDropdownContainsBundledPlugins(): void {
    $entity = WebhookSourceType::create([
      'id' => '',
      'label' => '',
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    $options = $built['verification']['verification_plugin']['#options'];

    $this->assertArrayHasKey('hmac_verification', $options);
    $this->assertArrayHasKey('api_key_verification', $options);
    $this->assertArrayHasKey('domain_whitelist_verification', $options);
  }

  /**
   * Tests that the form edit loads an existing verification_plugin default value.
   */
  public function testFormEditLoadsExistingVerificationPlugin(): void {
    WebhookSourceType::create([
      'id' => 'hmac_source',
      'label' => 'HMAC Source',
      'verification_plugin' => 'hmac_verification',
      'verification_config' => ['secret' => 'abc123', 'header' => 'X-Hub-Signature-256'],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = WebhookSourceType::load('hmac_source');

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    $this->assertSame(
      'hmac_verification',
      $built['verification']['verification_plugin']['#default_value'],
    );
  }

  /**
   * Tests that the form save clears verification_config when no plugin is selected.
   */
  public function testSaveClearsVerificationConfigWhenNoPluginSelected(): void {
    WebhookSourceType::create([
      'id' => 'previously_hmac',
      'label' => 'Previously HMAC',
      'verification_plugin' => 'hmac_verification',
      'verification_config' => ['secret' => 'old_secret', 'header' => 'X-Hub-Signature-256'],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = WebhookSourceType::load('previously_hmac');

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $form_state->setValue('label', 'Previously HMAC');
    $form_state->setValue('id', 'previously_hmac');
    $form_state->setValue('verification_plugin', '');

    $form_array = $form->buildForm([], $form_state);

    try {
      $form->save($form_array, $form_state);
    }
    catch (UndefinedLinkTemplateException) {
      // Expected when no endpoint route parameter is present.
    }

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $saved */
    $saved = WebhookSourceType::load('previously_hmac');

    $this->assertSame([], $saved->getVerificationConfig());
  }

  /**
   * Tests that the verification config default values are loaded from the entity.
   *
   * When editing a source type with a saved verification plugin configuration,
   * the plugin's form fields must show the stored values as their default.
   */
  public function testEditFormShowsStoredVerificationConfigDefaultValues(): void {
    WebhookSourceType::create([
      'id' => 'hmac_config_source',
      'label' => 'HMAC Config Source',
      'verification_plugin' => 'hmac_verification',
      'verification_config' => ['secret' => 'stored_secret', 'header' => 'X-My-Signature'],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = WebhookSourceType::load('hmac_config_source');

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    $this->assertSame('stored_secret', $built['verification']['verification_config']['secret']['#default_value']);
    $this->assertSame('X-My-Signature', $built['verification']['verification_config']['header']['#default_value']);
  }

  /**
   * Tests that addSourceTypeToEndpoint attaches the source type and persists.
   */
  public function testAddSourceTypeToEndpointAttachesSourceTypeAndSavesEndpoint(): void {
    WebhookSourceType::create([
      'id' => 'new_source',
      'label' => 'New Source',
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint */
    $endpoint = WebhookEndpoint::load('node_endpoint');
    $this->assertFalse($endpoint->hasSourceType('new_source'));

    $form = $this->getForm();

    $reflection = new \ReflectionMethod($form, 'addSourceTypeToEndpoint');
    $reflection->setAccessible(TRUE);
    $reflection->invokeArgs($form, ['new_source', $endpoint]);

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $reloaded */
    $reloaded = WebhookEndpoint::load('node_endpoint');

    $this->assertTrue($reloaded->hasSourceType('new_source'));
  }

  /**
   * Tests that save() stores the endpoint reference on the entity.
   */
  public function testSaveStoresEndpointReferenceOnEntity(): void {
    $entity = WebhookSourceType::create([
      'id' => 'endpoint_ref_source',
      'label' => 'Endpoint Ref Source',
      'verification_plugin' => '',
      'verification_config' => [],
      'endpoint' => 'node_endpoint',
    ]);
    $entity->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $saved */
    $saved = WebhookSourceType::load('endpoint_ref_source');

    $this->assertSame('node_endpoint', $saved->getEndpointId());
  }

  /**
   * Tests that save() persists verification config values entered via the form.
   */
  public function testSaveStoresVerificationConfigValuesFromSubform(): void {
    $entity = WebhookSourceType::create([
      'id' => 'hmac_save_source',
      'label' => 'HMAC Save Source',
      'verification_plugin' => 'hmac_verification',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $form_state->setValue('label', 'HMAC Save Source');
    $form_state->setValue('id', 'hmac_save_source');
    $form_state->setValue('verification_plugin', 'hmac_verification');
    $form_state->setValue('verification_config', [
      'secret' => 'my_secret_value',
      'header' => 'X-Custom-Sig',
    ]);

    $form_array = $form->buildForm([], $form_state);

    $form_array['#parents'] = [];
    $form_array['verification']['verification_config']['#parents'] = ['verification_config'];

    $form->submitForm($form_array, $form_state);

    try {
      $form->save($form_array, $form_state);
    }
    catch (UndefinedLinkTemplateException) {
      // Expected when no endpoint route parameter is present.
    }

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $saved */
    $saved = WebhookSourceType::load('hmac_save_source');

    $this->assertNotNull($saved);
    $config = $saved->getVerificationConfig();
    $this->assertSame('my_secret_value', $config['secret']);
    $this->assertSame('X-Custom-Sig', $config['header']);
  }

  /**
   * Tests that save() persists the operation value submitted via the form.
   */
  public function testSavePersistsOperationValueFromFormState(): void {
    $entity = WebhookSourceType::create([
      'id' => 'delete_source',
      'label' => 'Delete Source',
      'operation' => 'upsert',
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $form_state->setValue('label', 'Delete Source');
    $form_state->setValue('id', 'delete_source');
    $form_state->setValue('operation', 'delete');
    $form_state->setValue('verification_plugin', '');

    $form_array = $form->buildForm([], $form_state);

    try {
      $form->save($form_array, $form_state);
    }
    catch (UndefinedLinkTemplateException) {
      // Expected when no endpoint route parameter is present.
    }

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $saved */
    $saved = WebhookSourceType::load('delete_source');

    $this->assertNotNull($saved);
    $this->assertSame('delete', $saved->getOperation());
  }

  /**
   * Tests that the verification config subform is present in the form when a plugin is selected.
   */
  public function testFormContainsVerificationConfigSubformWhenPluginIsSelected(): void {
    WebhookSourceType::create([
      'id' => 'hmac_edit_source',
      'label' => 'HMAC Edit Source',
      'verification_plugin' => 'hmac_verification',
      'verification_config' => ['secret' => 'test_secret', 'header' => 'X-Hub-Signature-256'],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = WebhookSourceType::load('hmac_edit_source');

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    $this->assertArrayHasKey('verification_config', $built['verification']);
    $this->assertArrayHasKey('secret', $built['verification']['verification_config']);
    $this->assertArrayHasKey('header', $built['verification']['verification_config']);
  }

}
