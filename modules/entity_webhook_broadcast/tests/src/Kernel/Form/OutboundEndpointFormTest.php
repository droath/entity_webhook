<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Drupal\entity_webhook_broadcast\Form\OutboundEndpointForm;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for OutboundEndpointForm: form submission creates config entity.
 *
 * Tests the form's business logic — specifically the submitForm() method's
 * transformation of checkboxes values into a filtered events array — by
 * calling form methods directly rather than through the full FormBuilder
 * pipeline (which requires a routed HTTP request environment).
 *
 * Key behaviour verified:
 * - submitForm() transforms the checkboxes structure into a filtered events list
 * - save() persists all field values to the config entity
 * - Editing an existing entity updates stored values
 *
 * @group entity_webhook_broadcast
 */
class OutboundEndpointFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'entity_webhook_broadcast',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
  }

  /**
   * Builds a fully-wired OutboundEndpointForm instance.
   *
   * Wires moduleHandler, entityTypeManager, stringTranslation, and messenger
   * which EntityForm requires during submit and save operations.
   *
   * @return \Drupal\entity_webhook_broadcast\Form\OutboundEndpointForm
   *   The configured form instance.
   */
  private function buildForm(): OutboundEndpointForm {
    $form = OutboundEndpointForm::create($this->container);
    $form->setModuleHandler($this->container->get('module_handler'));
    $form->setEntityTypeManager($this->container->get('entity_type.manager'));
    $form->setStringTranslation($this->container->get('string_translation'));
    $form->setMessenger($this->container->get('messenger'));
    return $form;
  }

  /**
   * Invokes the form's submitForm() and save() methods programmatically.
   *
   * This bypasses the full FormBuilder pipeline (which requires routing) and
   * directly calls the submit and save handlers, which is sufficient to verify
   * the entity persistence behavior.
   *
   * @param \Drupal\entity_webhook_broadcast\Form\OutboundEndpointForm $form
   *   The form instance with entity set.
   * @param \Drupal\Core\Form\FormState $formState
   *   The form state with submitted values.
   */
  private function submitAndSave(OutboundEndpointForm $form, FormState $formState): void {
    $formArray = [];
    $form->submitForm($formArray, $formState);
    $form->save($formArray, $formState);
  }

  /**
   * Tests that the add form creates a new OutboundEndpoint with all field values.
   */
  public function testFormSubmissionCreatesOutboundEndpointWithCorrectValues(): void {
    // Arrange
    $endpoint = OutboundEndpoint::create([]);
    $form = $this->buildForm();
    $form->setEntity($endpoint);
    $form->setOperation('add');

    $formState = new FormState();
    $formState->setValues([
      'label' => 'My Node Endpoint',
      'id' => 'my_node_endpoint',
      'entity_type' => 'node',
      'entity_bundle' => 'article',
      'events' => [
        'insert' => 'insert',
        'update' => 0,
        'delete' => 0,
      ],
      'status' => TRUE,
    ]);

    // Act
    $this->submitAndSave($form, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $loaded */
    $loaded = OutboundEndpoint::load('my_node_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $loaded);
    $this->assertSame('My Node Endpoint', $loaded->label());
    $this->assertSame('node', $loaded->getWatchedEntityType());
    $this->assertSame('article', $loaded->getEntityBundle());
    $this->assertTrue($loaded->isEnabled());
    $this->assertContains('insert', $loaded->getEvents());
    $this->assertNotContains('update', $loaded->getEvents());
    $this->assertNotContains('delete', $loaded->getEvents());
  }

  /**
   * Tests that submitForm() filters unchecked events from the checkboxes array.
   *
   * OutboundEndpointForm::submitForm() calls array_keys(array_filter(...))
   * on the events checkboxes values. Unchecked boxes yield falsy values (0)
   * which are filtered out, leaving only the enabled event names.
   */
  public function testFormSubmissionFiltersUncheckedEventsFromSavedEntity(): void {
    // Arrange
    $endpoint = OutboundEndpoint::create([]);
    $form = $this->buildForm();
    $form->setEntity($endpoint);
    $form->setOperation('add');

    $formState = new FormState();
    $formState->setValues([
      'label' => 'All Events Endpoint',
      'id' => 'all_events_endpoint',
      'entity_type' => 'user',
      'entity_bundle' => '',
      'events' => [
        'insert' => 'insert',
        'update' => 'update',
        'delete' => 'delete',
      ],
      'status' => FALSE,
    ]);

    // Act
    $this->submitAndSave($form, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $loaded */
    $loaded = OutboundEndpoint::load('all_events_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $loaded);
    $events = $loaded->getEvents();
    $this->assertCount(3, $events);
    $this->assertContains('insert', $events);
    $this->assertContains('update', $events);
    $this->assertContains('delete', $events);
    $this->assertFalse($loaded->isEnabled());
  }

  /**
   * Tests that only checked events are saved when the form has a mixed selection.
   *
   * The checkboxes element returns 0 for unchecked boxes. submitForm() must
   * filter these out, leaving only the keys whose values are truthy.
   */
  public function testFormSubmissionSavesOnlyCheckedEventsWhenMixedSelection(): void {
    // Arrange
    $endpoint = OutboundEndpoint::create([]);
    $form = $this->buildForm();
    $form->setEntity($endpoint);
    $form->setOperation('add');

    $formState = new FormState();
    $formState->setValues([
      'label' => 'Insert Delete Endpoint',
      'id' => 'insert_delete_endpoint',
      'entity_type' => 'node',
      'entity_bundle' => '',
      'events' => [
        'insert' => 'insert',
        'update' => 0,
        'delete' => 'delete',
      ],
      'status' => TRUE,
    ]);

    // Act
    $this->submitAndSave($form, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $loaded */
    $loaded = OutboundEndpoint::load('insert_delete_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $loaded);
    $events = $loaded->getEvents();
    $this->assertCount(2, $events);
    $this->assertContains('insert', $events);
    $this->assertContains('delete', $events);
    $this->assertNotContains('update', $events);
  }

  /**
   * Tests that the edit form persists updated field values to the config entity.
   */
  public function testEditFormSubmissionUpdatesExistingEndpointValues(): void {
    // Arrange — create the entity directly in config storage.
    OutboundEndpoint::create([
      'id' => 'updatable_endpoint',
      'label' => 'Original Label',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
    ])->save();

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $existing */
    $existing = OutboundEndpoint::load('updatable_endpoint');

    $form = $this->buildForm();
    $form->setEntity($existing);
    $form->setOperation('edit');

    $formState = new FormState();
    $formState->setValues([
      'label' => 'Updated Label',
      'id' => 'updatable_endpoint',
      'entity_type' => 'user',
      'entity_bundle' => '',
      'events' => [
        'insert' => 0,
        'update' => 'update',
        'delete' => 0,
      ],
      'status' => FALSE,
    ]);

    // Act
    $this->submitAndSave($form, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $reloaded */
    $reloaded = OutboundEndpoint::load('updatable_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $reloaded);
    $this->assertSame('Updated Label', $reloaded->label());
    $this->assertSame('user', $reloaded->getWatchedEntityType());
    $this->assertFalse($reloaded->isEnabled());
    $this->assertContains('update', $reloaded->getEvents());
    $this->assertNotContains('insert', $reloaded->getEvents());
  }

}
