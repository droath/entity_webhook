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
 * Kernel tests for OutboundEndpointForm condition filtering.
 *
 * Verifies that buildConditionsSection() and getApplicableConditionDefinitions()
 * only expose condition plugins relevant to the selected entity type, and that
 * saving an endpoint with a configured entity_bundle:node condition does not
 * produce validation errors from irrelevant unconfigured condition plugins.
 *
 * @group entity_webhook_broadcast
 */
class OutboundEndpointFormConditionFilterTest extends KernelTestBase {

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
   * Tests that only node-relevant conditions appear for a node endpoint.
   *
   * For an endpoint watching the 'node' entity type, getApplicableConditionDefinitions()
   * must include entity_bundle:node and must exclude entity_bundle:comment,
   * entity_bundle:user, and similar derivatives for other entity types.
   */
  public function testGetApplicableConditionDefinitionsFiltersToNodeEntityType(): void {
    // Arrange
    $endpoint = OutboundEndpoint::create([
      'id' => 'filter_test_endpoint',
      'entity_type' => 'node',
    ]);
    $form = $this->buildForm();
    $form->setEntity($endpoint);
    $form->setOperation('add');

    // Act — call the protected method directly via Reflection.
    $method = new \ReflectionMethod(OutboundEndpointForm::class, 'getApplicableConditionDefinitions');
    /** @var array<string, mixed> $definitions */
    $definitions = $method->invoke($form, 'node');

    // Assert — entity_bundle:node is present.
    $this->assertArrayHasKey('entity_bundle:node', $definitions, 'entity_bundle:node must appear for node endpoints.');

    // Assert — other entity_bundle derivatives are absent.
    $this->assertArrayNotHasKey('entity_bundle:user', $definitions, 'entity_bundle:user must not appear for node endpoints.');
    foreach (array_keys($definitions) as $conditionId) {
      if (str_starts_with((string) $conditionId, 'entity_bundle:')) {
        $this->assertSame(
          'entity_bundle:node',
          $conditionId,
          "Only entity_bundle:node is allowed; found $conditionId.",
        );
      }
    }
  }

  /**
   * Tests that buildConditionsSection() renders no conditions when no entity type is selected.
   *
   * When the form is loaded without a selected entity type, the conditions
   * section must be empty to avoid rendering irrelevant condition subforms.
   */
  public function testBuildConditionsSectionIsEmptyWhenNoEntityTypeSelected(): void {
    // Arrange — entity with no entity_type set.
    $endpoint = OutboundEndpoint::create([]);
    $form = $this->buildForm();
    $form->setEntity($endpoint);
    $form->setOperation('add');

    $formState = new FormState();

    // Act
    $method = new \ReflectionMethod(OutboundEndpointForm::class, 'buildConditionsSection');
    /** @var array<string, mixed> $section */
    $section = $method->invoke($form, $formState);

    // Assert — no condition plugin subforms rendered beyond the section wrapper keys.
    $sectionKeys = array_filter(
      array_keys($section),
      static fn(string $key) => !str_starts_with($key, '#'),
    );
    $this->assertEmpty($sectionKeys, 'No condition subforms must be rendered when entity type is not selected.');
  }

  /**
   * Tests that saving an endpoint with entity_bundle:node condition causes no validation errors.
   *
   * The form must only register condition plugin instances that were rendered,
   * so that submitConditionsSection() only processes those instances and
   * validateConditionsSection() cannot receive invalid values from plugins
   * whose subforms were never shown to the user.
   */
  public function testSavingEndpointWithEntityBundleNodeConditionProducesNoValidationErrors(): void {
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

    // Act — submitForm() must not set any form errors from unconfigured plugins.
    $form->submitForm($formArray, $formState);
    $form->save($formArray, $formState);

    // Assert — no form errors were set, and entity is persisted correctly.
    $this->assertFalse(
      $formState->hasAnyErrors(),
      'No form errors must be set when saving with a valid entity_bundle:node condition.',
    );

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null $loaded */
    $loaded = OutboundEndpoint::load('article_bundle_endpoint');
    $this->assertInstanceOf(OutboundEndpointInterface::class, $loaded);

    $conditionsConfig = $loaded->getConditions()->getConfiguration();
    $this->assertArrayHasKey(
      $conditionId,
      $conditionsConfig,
      'entity_bundle:node must be persisted when submitted with non-default bundles.',
    );
    $this->assertSame(
      ['article' => 'article'],
      $conditionsConfig[$conditionId]['bundles'],
    );
  }

}
