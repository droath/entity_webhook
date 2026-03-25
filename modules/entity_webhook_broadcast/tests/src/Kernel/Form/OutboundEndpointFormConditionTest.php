<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook_broadcast\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Drupal\entity_webhook_broadcast\Form\OutboundEndpointForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for OutboundEndpointForm condition persistence.
 *
 * Verifies that condition plugin configuration submitted through the form is
 * correctly persisted to the OutboundEndpoint config entity, and that
 * round-tripping a saved entity with conditions preserves that configuration.
 *
 * These tests call submitForm() and save() directly, bypassing the full
 * FormBuilder pipeline (which requires a routed HTTP request environment), and
 * constructing the minimal form array structure that SubformState requires
 * when processing condition plugin subforms.
 *
 * The entity_bundle:node condition is used throughout because it has a
 * meaningful custom config key (bundles) beyond the standard meta-keys
 * (id, negate, context_mapping), making it suitable for testing
 * getActiveConditions() filtering logic.
 *
 * @group entity_webhook_broadcast
 */
class OutboundEndpointFormConditionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_webhook',
    'entity_webhook_broadcast',
    'user',
    'system',
    'node',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
  }

  /**
   * Builds a fully-wired OutboundEndpointForm instance.
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
   * Builds the minimal form array needed for condition subform processing.
   *
   * SubformState::createForSubform() requires both the parent form and the
   * condition subform to carry #parents arrays so it can compute relative
   * parents when extracting scoped values from the complete form state.
   *
   * @param string[] $conditionIds
   *   The condition plugin IDs for which to create subform entries.
   *
   * @return array<string, mixed>
   *   A form array suitable for passing to submitForm() / save().
   */
  private function buildFormArrayForConditions(array $conditionIds): array {
    $form = ['#parents' => []];
    $form['conditions'] = [];
    foreach ($conditionIds as $conditionId) {
      $form['conditions'][$conditionId] = [
        '#parents' => ['conditions', $conditionId],
      ];
    }
    return $form;
  }

  /**
   * Invokes the form's submitForm() and save() methods programmatically.
   *
   * @param \Drupal\entity_webhook_broadcast\Form\OutboundEndpointForm $form
   *   The form instance with entity set.
   * @param array<string, mixed> $formArray
   *   The form structure array (must carry #parents for condition subforms).
   * @param \Drupal\Core\Form\FormState $formState
   *   The form state with submitted values.
   */
  private function submitAndSave(OutboundEndpointForm $form, array $formArray, FormState $formState): void {
    $form->submitForm($formArray, $formState);
    $form->save($formArray, $formState);
  }

  /**
   * Tests that a configured condition with non-default values is persisted.
   *
   * Submits entity_bundle:node with bundles=['article'], which is a non-default
   * value. After save and reload, the condition must appear in both
   * getConditions()->getConfiguration() and getActiveConditions().
   */
  public function testFormSubmissionWithConfiguredConditionPersistsConfig(): void {
    // Arrange
    $conditionId = 'entity_bundle:node';
    $endpoint = OutboundEndpoint::create([]);
    $form = $this->buildForm();
    $form->setEntity($endpoint);
    $form->setOperation('add');

    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    $condition = $this->container
      ->get('plugin.manager.condition')
      ->createInstance($conditionId, []);

    $formState = new FormState();
    $formState->set(['conditions', $conditionId], $condition);
    $formState->setValues([
      'label' => 'Article Bundle Endpoint',
      'id' => 'article_bundle_endpoint',
      'entity_type' => 'node',
      'entity_bundle' => '',
      'events' => [
        'insert' => 'insert',
        'update' => 0,
        'delete' => 0,
      ],
      'status' => TRUE,
      'conditions' => [
        $conditionId => [
          'bundles' => ['article' => 'article'],
          'negate' => FALSE,
        ],
      ],
    ]);

    $formArray = $this->buildFormArrayForConditions([$conditionId]);

    // Act
    $this->submitAndSave($form, $formArray, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $loaded */
    $loaded = OutboundEndpoint::load('article_bundle_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $loaded);

    $conditionsConfig = $loaded->getConditions()->getConfiguration();
    $this->assertArrayHasKey(
      $conditionId,
      $conditionsConfig,
      'Configured condition must appear in the conditions collection after save.',
    );

    $activeConditions = $loaded->getActiveConditions();
    $this->assertArrayHasKey(
      $conditionId,
      $activeConditions,
      'Condition with non-default bundles config must appear in getActiveConditions().',
    );
  }

  /**
   * Tests that a condition with empty bundles config is stripped by ConditionPluginCollection.
   *
   * Submitting entity_bundle:node with empty bundles (the default) results in
   * ConditionPluginCollection stripping it during preSave. The conditions
   * collection is empty and getActiveConditions() returns an empty array.
   */
  public function testFormSubmissionWithDefaultConditionConfigIsStrippedByPreSave(): void {
    // Arrange
    $conditionId = 'entity_bundle:node';
    $endpoint = OutboundEndpoint::create([]);
    $form = $this->buildForm();
    $form->setEntity($endpoint);
    $form->setOperation('add');

    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    $condition = $this->container
      ->get('plugin.manager.condition')
      ->createInstance($conditionId, []);

    $formState = new FormState();
    $formState->set(['conditions', $conditionId], $condition);
    $formState->setValues([
      'label' => 'Empty Bundles Endpoint',
      'id' => 'empty_bundles_endpoint',
      'entity_type' => 'node',
      'entity_bundle' => '',
      'events' => ['insert' => 'insert', 'update' => 0, 'delete' => 0],
      'status' => TRUE,
      // Empty bundles matches the defaultConfiguration(): stripped by preSave.
      'conditions' => [
        $conditionId => [
          'bundles' => [],
          'negate' => FALSE,
        ],
      ],
    ]);

    $formArray = $this->buildFormArrayForConditions([$conditionId]);

    // Act
    $this->submitAndSave($form, $formArray, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $loaded */
    $loaded = OutboundEndpoint::load('empty_bundles_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $loaded);

    $this->assertSame(
      0,
      $loaded->getConditions()->count(),
      'Condition with default (empty) bundles is stripped by ConditionPluginCollection during preSave.',
    );
    $this->assertSame(
      [],
      $loaded->getActiveConditions(),
      'getActiveConditions() must return an empty array when no conditions have non-default config.',
    );
  }

  /**
   * Tests that re-editing an entity shows condition config with saved values.
   *
   * Creates an endpoint with entity_bundle:node configured for 'article',
   * loads it, and verifies that getActiveConditions() returns the condition
   * with the saved bundles configuration intact.
   */
  public function testReEditingEntityShowsConditionWithSavedValues(): void {
    // Arrange — create endpoint with a configured bundle condition.
    OutboundEndpoint::create([
      'id' => 'roundtrip_endpoint',
      'label' => 'Round-Trip Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
      'conditions' => [
        'entity_bundle:node' => [
          'id' => 'entity_bundle:node',
          'negate' => FALSE,
          'bundles' => ['article' => 'article'],
        ],
      ],
    ])->save();

    // Act
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $existing */
    $existing = OutboundEndpoint::load('roundtrip_endpoint');

    // Assert — getActiveConditions() returns the condition on re-edit.
    $activeConditions = $existing->getActiveConditions();
    $this->assertArrayHasKey(
      'entity_bundle:node',
      $activeConditions,
      'entity_bundle:node must appear in getActiveConditions() after reload.',
    );

    // Assert — the bundles config round-trips correctly.
    $conditionsConfig = $existing->getConditions()->getConfiguration();
    $this->assertArrayHasKey('entity_bundle:node', $conditionsConfig);
    $this->assertSame(
      ['article' => 'article'],
      $conditionsConfig['entity_bundle:node']['bundles'],
      'Bundles config must match the saved values after reload.',
    );
  }

  /**
   * Tests that re-saving an entity via the form preserves the condition.
   *
   * Creates an endpoint with a configured bundle condition, loads it, submits
   * the form again with the same values, and verifies the condition survives
   * the round-trip through form submission.
   */
  public function testReSavingEntityViaFormPreservesCondition(): void {
    // Arrange — create endpoint with a configured bundle condition.
    OutboundEndpoint::create([
      'id' => 'resave_endpoint',
      'label' => 'Re-Save Endpoint',
      'status' => TRUE,
      'entity_type' => 'node',
      'entity_bundle' => NULL,
      'events' => ['insert'],
      'conditions' => [
        'entity_bundle:node' => [
          'id' => 'entity_bundle:node',
          'negate' => FALSE,
          'bundles' => ['article' => 'article'],
        ],
      ],
    ])->save();

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $existing */
    $existing = OutboundEndpoint::load('resave_endpoint');

    $conditionId = 'entity_bundle:node';
    $conditionsConfig = $existing->getConditions()->getConfiguration();

    $form = $this->buildForm();
    $form->setEntity($existing);
    $form->setOperation('edit');

    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    $condition = $this->container
      ->get('plugin.manager.condition')
      ->createInstance($conditionId, $conditionsConfig[$conditionId]);

    $formState = new FormState();
    $formState->set(['conditions', $conditionId], $condition);
    $formState->setValues([
      'label' => 'Re-Save Endpoint',
      'id' => 'resave_endpoint',
      'entity_type' => 'node',
      'entity_bundle' => '',
      'events' => ['insert' => 'insert', 'update' => 0, 'delete' => 0],
      'status' => TRUE,
      'conditions' => [
        $conditionId => [
          'bundles' => ['article' => 'article'],
          'negate' => FALSE,
        ],
      ],
    ]);

    $formArray = $this->buildFormArrayForConditions([$conditionId]);

    // Act
    $this->submitAndSave($form, $formArray, $formState);

    // Assert
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $reloaded */
    $reloaded = OutboundEndpoint::load('resave_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $reloaded);

    $activeConditions = $reloaded->getActiveConditions();
    $this->assertArrayHasKey(
      $conditionId,
      $activeConditions,
      'Condition must still appear in getActiveConditions() after a form re-save.',
    );

    $conditionsAfterResave = $reloaded->getConditions()->getConfiguration();
    $this->assertArrayHasKey($conditionId, $conditionsAfterResave);
    $this->assertSame(
      ['article' => 'article'],
      $conditionsAfterResave[$conditionId]['bundles'],
      'Bundles config must be preserved after a form re-save.',
    );
  }

  /**
   * Tests that form submission with no conditions saves an empty conditions state.
   *
   * When no condition subform values are present in the form state, the entity
   * must be saved with empty conditions, ensuring backward compatibility for
   * endpoints that have no conditions configured.
   */
  public function testFormSubmissionWithNoConditionsSavesEmptyState(): void {
    // Arrange — no conditions in form state values.
    $endpoint = OutboundEndpoint::create([]);
    $form = $this->buildForm();
    $form->setEntity($endpoint);
    $form->setOperation('add');

    $formState = new FormState();
    $formState->setValues([
      'label' => 'No Conditions Endpoint',
      'id' => 'no_conditions_endpoint',
      'entity_type' => 'node',
      'entity_bundle' => '',
      'events' => ['insert' => 'insert', 'update' => 0, 'delete' => 0],
      'status' => TRUE,
      // No 'conditions' key — simulates a form submission with no condition values.
    ]);

    $formArray = [];

    // Act
    $this->submitAndSave($form, $formArray, $formState);

    // Assert — entity saved with zero conditions.
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $loaded */
    $loaded = OutboundEndpoint::load('no_conditions_endpoint');

    $this->assertInstanceOf(OutboundEndpointInterface::class, $loaded);
    $this->assertSame(
      0,
      $loaded->getConditions()->count(),
      'Entity must have no conditions when none were submitted.',
    );
    $this->assertSame(
      [],
      $loaded->getActiveConditions(),
      'getActiveConditions() must return an empty array when no conditions were submitted.',
    );
  }

}
