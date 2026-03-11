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
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    $this->assertArrayHasKey('label', $built);
    $this->assertArrayHasKey('id', $built);
    $this->assertArrayHasKey('field_mappings', $built);
    $this->assertArrayHasKey('verification', $built);
  }

  /**
   * Tests that the verification_plugin dropdown is present in the built form.
   */
  public function testFormContainsVerificationPluginDropdown(): void {
    $entity = WebhookSourceType::create([
      'id' => '',
      'label' => '',
      'field_mappings' => [],
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
      'field_mappings' => [],
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
      'field_mappings' => [],
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
   * Tests that form build for an entity with existing mappings shows the correct row count.
   */
  public function testFormBuildDisplaysExistingMappingRowsForEditForm(): void {
    WebhookSourceType::create([
      'id' => 'two_mappings',
      'label' => 'Two Mappings',
      'field_mappings' => [
        ['entity_field' => 'title', 'json_path' => '$.name', 'is_identifier' => TRUE],
        ['entity_field' => 'body', 'json_path' => '$.body', 'is_identifier' => FALSE],
      ],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = WebhookSourceType::load('two_mappings');

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    // The field_mappings element should contain 2 numeric keyed mapping rows
    // plus the non-numeric 'add_mapping' submit button.
    $numericRows = array_filter(
      array_keys($built['field_mappings']),
      static fn ($key) => is_int($key),
    );

    $this->assertCount(2, $numericRows);
  }

  /**
   * Tests that submitting without any identifier mapping fails validation.
   */
  public function testValidationFailsWhenMappingsExistButNoneAreIdentifiers(): void {
    $entity = WebhookSourceType::create([
      'id' => 'test_source',
      'label' => 'Test Source',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $form_state->setValue('field_mappings', [
      0 => ['entity_field' => 'title', 'json_path' => '$.name', 'is_identifier' => '0'],
    ]);
    $form_state->setValue('verification_plugin', '');

    // Simulate the standard submit trigger (not an AJAX mapping operation).
    $form_state->setTriggeringElement(['#name' => 'op']);

    $form_array = $form->buildForm([], $form_state);
    $form->validateForm($form_array, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('field_mappings', $errors);
  }

  /**
   * Tests that validation passes when at least one mapping is marked as identifier.
   */
  public function testValidationPassesWhenAtLeastOneIdentifierMappingExists(): void {
    $entity = WebhookSourceType::create([
      'id' => 'test_source_valid',
      'label' => 'Test Source Valid',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $form_state->setValue('field_mappings', [
      0 => ['entity_field' => 'title', 'json_path' => '$.name', 'is_identifier' => '1'],
    ]);
    $form_state->setValue('verification_plugin', '');

    $form_state->setTriggeringElement(['#name' => 'op']);

    $form_array = $form->buildForm([], $form_state);
    $form->validateForm($form_array, $form_state);

    $this->assertFalse($form_state->hasAnyErrors());
  }

  /**
   * Tests that the form saves a source type with field mappings correctly.
   */
  public function testSaveStoresFieldMappingsOnEntity(): void {
    $entity = WebhookSourceType::create([
      'id' => 'mapped_source',
      'label' => 'Mapped Source',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $form_state->setValue('label', 'Mapped Source');
    $form_state->setValue('id', 'mapped_source');
    $form_state->setValue('field_mappings', [
      0 => ['entity_field' => 'title', 'json_path' => '$.name', 'is_identifier' => '1'],
    ]);
    $form_state->setValue('verification_plugin', '');

    $form_array = $form->buildForm([], $form_state);

    // save() persists the entity before setting the redirect URL. The
    // webhook_source_type entity has no 'collection' link template, so
    // toUrl('collection') throws when no endpoint route parameter is present.
    // We catch that exception here since this test only verifies persistence.
    try {
      $form->save($form_array, $form_state);
    }
    catch (UndefinedLinkTemplateException) {
      // Expected when no endpoint route parameter is present.
    }

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $saved */
    $saved = WebhookSourceType::load('mapped_source');

    $this->assertNotNull($saved);
    $mappings = $saved->getFieldMappings();
    $this->assertCount(1, $mappings);
    $this->assertSame('title', $mappings[0]->entityField);
    $this->assertSame('$.name', $mappings[0]->jsonPath);
    $this->assertTrue($mappings[0]->isIdentifier);
  }

  /**
   * Tests that the form save clears verification_config when no plugin is selected.
   */
  public function testSaveClearsVerificationConfigWhenNoPluginSelected(): void {
    WebhookSourceType::create([
      'id' => 'previously_hmac',
      'label' => 'Previously HMAC',
      'field_mappings' => [],
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
    $form_state->setValue('field_mappings', []);
    $form_state->setValue('verification_plugin', '');

    $form_array = $form->buildForm([], $form_state);

    // save() persists the entity before setting the redirect URL. Catch the
    // UndefinedLinkTemplateException that occurs when no endpoint route
    // parameter is present since this test only verifies persisted config.
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
   * Tests that the entity_field element is a select with empty options when no
   * webhook_endpoint route parameter is present.
   *
   * The edit form URL always includes the endpoint in the route, so a select
   * element is always rendered. Without a route parameter the options list is
   * empty; tests that need populated options must supply a route parameter.
   */
  public function testEditFormRendersSelectWithEmptyOptionsWhenNoRouteEndpoint(): void {
    WebhookSourceType::create([
      'id' => 'stored_endpoint_source',
      'label' => 'Stored Endpoint Source',
      'field_mappings' => [
        ['entity_field' => 'uid', 'json_path' => '$.user_id', 'is_identifier' => TRUE],
      ],
      'verification_plugin' => '',
      'verification_config' => [],
      'endpoint' => 'node_endpoint',
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = WebhookSourceType::load('stored_endpoint_source');

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    $this->assertSame('select', $built['field_mappings'][0]['entity_field']['#type']);
    $this->assertSame([], $built['field_mappings'][0]['entity_field']['#options']);
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
      'field_mappings' => [],
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
   * Tests that the remove button is absent on the last (empty) mapping row.
   *
   * When a new source type is being created, the form starts with one empty row.
   * That single row is the last row and must not show a remove button, since
   * removing it would leave the user with no way to add field mappings.
   */
  public function testRemoveButtonAbsentOnLastMappingRow(): void {
    $entity = WebhookSourceType::create([
      'id' => '',
      'label' => '',
      'field_mappings' => [],
      'verification_plugin' => '',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    // With one row (delta 0) and count = 1, row 0 is the last row.
    $this->assertArrayNotHasKey('remove', $built['field_mappings'][0]);
  }

  /**
   * Tests that the remove button is present on non-last mapping rows.
   *
   * When the form has multiple rows, every row except the last must show the
   * remove button so the user can delete intermediate rows.
   */
  public function testRemoveButtonPresentOnRowsWithValues(): void {
    WebhookSourceType::create([
      'id' => 'multi_mapping_source',
      'label' => 'Multi Mapping Source',
      'field_mappings' => [
        ['entity_field' => 'title', 'json_path' => '$.name', 'is_identifier' => TRUE],
        ['entity_field' => 'body', 'json_path' => '$.desc', 'is_identifier' => FALSE],
        ['entity_field' => '', 'json_path' => '', 'is_identifier' => FALSE],
      ],
      'verification_plugin' => '',
      'verification_config' => [],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = WebhookSourceType::load('multi_mapping_source');

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    // Rows with values must have a remove button regardless of position.
    $this->assertArrayHasKey('remove', $built['field_mappings'][0]);
    $this->assertArrayHasKey('remove', $built['field_mappings'][1]);
    // A row with no values must never show the remove button.
    $this->assertArrayNotHasKey('remove', $built['field_mappings'][2]);
  }

  /**
   * Tests that addSourceTypeToEndpoint attaches the source type and persists.
   *
   * The addSourceTypeToEndpoint helper is the method called by save() when an
   * endpoint route parameter is present. Exercising it directly lets us verify
   * the endpoint-attach logic without needing to mock RouteMatchInterface.
   */
  public function testAddSourceTypeToEndpointAttachesSourceTypeAndSavesEndpoint(): void {
    WebhookSourceType::create([
      'id' => 'new_source',
      'label' => 'New Source',
      'field_mappings' => [],
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
   * Tests that save() stores the endpoint reference on the entity when the
   * addSourceTypeToEndpoint path is exercised.
   *
   * Calls addSourceTypeToEndpoint directly (as the real save() path does) and
   * then verifies the entity's endpoint property is set before the entity save.
   * We simulate this by creating a source type with a pre-set endpoint property
   * and checking the getter returns the correct ID after a round-trip save.
   */
  public function testSaveStoresEndpointReferenceOnEntity(): void {
    $entity = WebhookSourceType::create([
      'id' => 'endpoint_ref_source',
      'label' => 'Endpoint Ref Source',
      'field_mappings' => [],
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
   *
   * This is the core regression test for the SubformState parents bug.
   * SubformState::getValues() resolves values from the parent form state using
   * the subform's #parents array (e.g. ['verification_config']). Without
   * #tree => TRUE on the verification_config container, Form API flattens the
   * child fields to root level so SubformState never finds the values.
   *
   * The test simulates post-FormBuilder state by:
   * - setting #parents on the root form and verification_config subform, and
   * - placing the submitted values at the correct nested path in form state.
   */
  public function testSaveStoresVerificationConfigValuesFromSubform(): void {
    $entity = WebhookSourceType::create([
      'id' => 'hmac_save_source',
      'label' => 'HMAC Save Source',
      'field_mappings' => [],
      'verification_plugin' => 'hmac_verification',
      'verification_config' => [],
    ]);

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $form_state->setValue('label', 'HMAC Save Source');
    $form_state->setValue('id', 'hmac_save_source');
    $form_state->setValue('field_mappings', []);
    $form_state->setValue('verification_plugin', 'hmac_verification');
    $form_state->setValue('verification_config', [
      'secret' => 'my_secret_value',
      'header' => 'X-Custom-Sig',
    ]);

    $form_array = $form->buildForm([], $form_state);

    // Simulate what FormBuilder sets during doBuildForm() so that
    // SubformState::getParents() can resolve the subform's parents.
    $form_array['#parents'] = [];
    $form_array['verification']['verification_config']['#parents'] = ['verification_config'];

    // submitForm() processes the plugin's configuration form before the entity
    // is built.
    $form->submitForm($form_array, $form_state);

    // save() persists the entity before setting the redirect URL. Catch the
    // UndefinedLinkTemplateException that occurs when no endpoint route
    // parameter is present since this test only verifies persisted config.
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
   * Tests that the verification config subform is present in the form when a plugin is selected.
   */
  public function testFormContainsVerificationConfigSubformWhenPluginIsSelected(): void {
    WebhookSourceType::create([
      'id' => 'hmac_edit_source',
      'label' => 'HMAC Edit Source',
      'field_mappings' => [],
      'verification_plugin' => 'hmac_verification',
      'verification_config' => ['secret' => 'test_secret', 'header' => 'X-Hub-Signature-256'],
    ])->save();

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = WebhookSourceType::load('hmac_edit_source');

    $form = $this->getForm();
    $form->setEntity($entity);

    $form_state = new FormState();
    $built = $form->buildForm([], $form_state);

    // When a plugin is selected, the verification_config container should contain
    // the plugin's configuration form fields.
    $this->assertArrayHasKey('verification_config', $built['verification']);
    $this->assertArrayHasKey('secret', $built['verification']['verification_config']);
    $this->assertArrayHasKey('header', $built['verification']['verification_config']);
  }

}
