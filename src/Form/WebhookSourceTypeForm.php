<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Form;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\entity_webhook\Traits\AjaxFormStateTrait;
use Drupal\entity_webhook\Traits\ConfigEntityFormTrait;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface;
use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface;

/**
 * Provides the add/edit form for WebhookSourceType config entities.
 */
class WebhookSourceTypeForm extends EntityForm {
  use AjaxFormStateTrait;
  use ConfigEntityFormTrait;

  /**
   * The entity field manager service.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The webhook verification plugin manager.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected WebhookVerificationManagerInterface $verificationManager;

  /**
   * The field value mutation plugin manager.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected FieldValueMutationManagerInterface $mutationManager;

  /**
   * Constructs a WebhookSourceTypeForm.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface $verificationManager
   *   The webhook verification plugin manager.
   * @param \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $mutationManager
   *   The field value mutation plugin manager.
   */
  public function __construct(
    RouteMatchInterface $routeMatch,
    EntityFieldManagerInterface $entityFieldManager,
    WebhookVerificationManagerInterface $verificationManager,
    FieldValueMutationManagerInterface $mutationManager,
  ) {
    $this->routeMatch = $routeMatch;
    $this->entityFieldManager = $entityFieldManager;
    $this->verificationManager = $verificationManager;
    $this->mutationManager = $mutationManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.webhook_verification'),
      $container->get('plugin.manager.field_value_mutation'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = $this->entity;

    $form += $this->buildLabelIdElements(
      '\Drupal\entity_webhook\Entity\WebhookSourceType::load',
      $entity,
    );

    $form['field_mappings'] = [
      '#type' => 'details',
      '#title' => $this->t('Field Mappings'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#prefix' => '<div id="field-mappings-wrapper">',
      '#suffix' => '</div>',
    ];
    $endpoint = $this->resolveEndpoint();

    $userInput = $form_state->getUserInput();
    $inputMappings = $userInput['field_mappings'] ?? NULL;
    $existingMappings = $entity->get('field_mappings') ?? [];

    $mappingsCount = $this->resolveMappingsCount($existingMappings, $form_state);

    for ($delta = 0; $delta < $mappingsCount; $delta++) {
      $defaults = $this->resolveFieldMappingDefaults($delta, $existingMappings, $inputMappings);
      $form['field_mappings'][$delta] = $this->buildFieldMappingRow(
        $delta,
        $defaults,
        $endpoint,
      );
      $this->buildMutationConfigSubform(
        $form['field_mappings'][$delta],
        $form,
        $delta,
        $defaults,
        $form_state,
      );
    }

    $form['field_mappings']['add_mapping'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add field mapping'),
      '#submit' => [[$this, 'addFieldMappingRow']],
      '#ajax' => [
        'wrapper' => 'field-mappings-wrapper',
        'callback' => [$this, 'fieldMappingsCallback'],
      ],
      '#limit_validation_errors' => [],
    ];

    $form['verification'] = [
      '#type' => 'details',
      '#title' => $this->t('Verification'),
      '#open' => TRUE,
    ];

    $form['verification']['verification_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Verification Plugin'),
      '#options' => $this->verificationManager->getOptions(),
      '#default_value' => $entity->getVerificationPlugin(),
      '#empty_option' => $this->t('- None -'),
      '#empty_value' => '',
      '#description' => $this->t('The verification plugin to use for incoming requests. Leave empty for no verification.'),
      '#ajax' => [
        'wrapper' => 'verification-config-wrapper',
        'callback' => [$this, 'ajaxUpdateVerificationConfig'],
      ],
    ];

    $form['verification']['verification_config'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="verification-config-wrapper">',
      '#suffix' => '</div>',
    ];

    $selectedPlugin = $this->resolveSelectedVerificationPlugin($form_state);

    if ($selectedPlugin !== NULL && $selectedPlugin !== '') {
      $existingConfig = $entity->getVerificationConfig();
      $plugin = $this->verificationManager->createInstance($selectedPlugin, $existingConfig);

      if ($plugin instanceof PluginFormInterface) {
        $subform = ['#parents' => ['verification_config']];
        $subformState = SubformState::createForSubform(
          $subform,
          $form,
          $form_state,
        );
        $form['verification']['verification_config'] += $plugin->buildConfigurationForm(
          $subform,
          $subformState,
        );
      }
    }

    return $form;
  }

  /**
   * AJAX callback for the field mappings wrapper.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The field mappings element.
   */
  public function fieldMappingsCallback(array &$form, FormStateInterface $form_state): array {
    return $form['field_mappings'];
  }

  /**
   * AJAX callback that returns the verification_config container element.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The verification config container element.
   */
  public function ajaxUpdateVerificationConfig(array &$form, FormStateInterface $form_state): array {
    return $form['verification']['verification_config'];
  }

  /**
   * AJAX callback that returns the mutation_config container for a mapping row.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The mutation config container element for the triggering row.
   */
  public function ajaxUpdateMutationConfig(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $delta = $trigger['#parents'][1];

    return $form['field_mappings'][$delta]['mutation_config'];
  }

  /**
   * Submit handler for adding a new field mapping row.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addFieldMappingRow(array &$form, FormStateInterface $form_state): void {
    $count = $form_state->get('field_mappings_count') ?? 1;
    $form_state->set('field_mappings_count', $count + 1);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if ($this->isAjaxMappingOperation($form_state)) {
      return;
    }

    $this->validateIdentifierRequirement($form_state);
    $this->validatePluginConfigurationForm($form, $form_state);
    $this->validateMutationPluginForms($form, $form_state);
  }

  /**
   * Submit handler for removing a field mapping row.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeFieldMappingRow(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $triggeringElement = $form_state->getTriggeringElement();
    $name = $triggeringElement['#name'] ?? '';
    $delta = $this->parseDeltaFromElementName($name);

    $userInput = $form_state->getUserInput();
    $mappings = $userInput['field_mappings'] ?? [];
    unset($mappings[$delta]);
    $mappings = array_values($mappings);

    $userInput['field_mappings'] = $mappings;
    $form_state->setUserInput($userInput);

    $count = $form_state->get('field_mappings_count') ?? 1;
    $form_state->set('field_mappings_count', max(1, $count - 1));

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = $this->entity;

    $this->applyFieldMappings($form_state);
    $this->applyVerificationConfig($form_state);

    $endpoint = $this->resolveEndpoint();

    if ($endpoint instanceof WebhookEndpointInterface) {
      $entity->set('endpoint', $endpoint->id());
    }

    $status = parent::save($form, $form_state);

    if ($endpoint instanceof WebhookEndpointInterface) {
      $this->addSourceTypeToEndpoint($entity->id(), $endpoint);

      $this->messenger()
        ->addStatus($this->t('Source type %label has been created and added to %endpoint.', [
          '%label' => $entity->label(),
          '%endpoint' => $endpoint->label(),
        ]));

      $form_state->setRedirectUrl(Url::fromRoute('entity.webhook_endpoint.source_types', [
        'webhook_endpoint' => $endpoint->id(),
      ]));

      return $status;
    }

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Source type %label has been created.', ['%label' => $entity->label()])
        : $this->t('Source type %label has been updated.', ['%label' => $entity->label()]),
    );

    $form_state->setRedirectUrl($entity->toUrl('collection'));

    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->submitPluginConfigurationForm($form, $form_state);
    $this->submitMutationPluginForms($form, $form_state);

    parent::submitForm($form, $form_state);
  }

  /**
   * Resolves the active WebhookEndpoint from the current route parameter.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null
   *   The resolved endpoint, or NULL if the route does not carry one.
   */
  protected function resolveEndpoint(): ?WebhookEndpointInterface {
    $endpoint = $this->routeMatch->getParameter('webhook_endpoint');

    return $endpoint instanceof WebhookEndpointInterface ? $endpoint : NULL;
  }

  /**
   * Resolves the number of field mapping rows to render.
   *
   * On the initial page load the count is initialized from the entity's saved
   * mappings (minimum one) and stored in form state. Subsequent AJAX rebuilds
   * read the stored counter, which is incremented by addFieldMappingRow() and
   * decremented by removeFieldMappingRow().
   *
   * @param array<int, array<string, mixed>> $existingMappings
   *   The entity's stored field mappings.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return int
   *   The number of rows to render.
   */
  protected function resolveMappingsCount(
    array $existingMappings,
    FormStateInterface $form_state,
  ): int {
    $count = $form_state->get('field_mappings_count');

    if ($count === NULL) {
      $count = max(count($existingMappings), 1);
      $form_state->set('field_mappings_count', $count);
    }

    return $count;
  }

  /**
   * Adds the newly created source type to the given endpoint and saves it.
   *
   * @param string $sourceTypeId
   *   The source type machine name.
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint
   *   The endpoint to update.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function addSourceTypeToEndpoint(
    string $sourceTypeId,
    WebhookEndpointInterface $endpoint,
  ): void {
    $endpoint->addSourceType($sourceTypeId);
    $endpoint->save();
  }

  /**
   * Resolves the selected verification plugin ID across all form lifecycle
   * phases.
   *
   * Delegates to AjaxFormStateTrait::getFormStateValue() which checks user
   * input first for AJAX rebuilds, then form state values. Falls back to the
   * entity's stored plugin ID when neither source has a value.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return string|null
   *   The plugin ID, or NULL if no plugin is selected.
   */
  protected function resolveSelectedVerificationPlugin(
    FormStateInterface $form_state,
  ): ?string {
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = $this->entity;

    return $this->getFormStateValue(
      'verification_plugin',
      $form_state,
      $entity->getVerificationPlugin(),
    );
  }

  /**
   * Resolves the selected mutation plugin ID for a given mapping row.
   *
   * Checks user input first (for AJAX rebuilds) then form state values. Falls
   * back to the stored default from the entity or existing user input when
   * neither source has the key yet (initial page load).
   *
   * @param int $delta
   *   The row index.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $default
   *   The fallback plugin ID, typically from entity data.
   *
   * @return string|null
   *   The plugin ID, or NULL if no plugin is selected.
   */
  protected function resolveSelectedMutationPlugin(
    int $delta,
    FormStateInterface $form_state,
    string $default = '',
  ): ?string {
    return $this->getFormStateValue(
      ['field_mappings', $delta, 'mutation_plugin'],
      $form_state,
      $default,
    );
  }

  /**
   * Checks whether the triggering element is an AJAX mapping operation.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return bool
   *   TRUE if the trigger is an added or remove mapping AJAX button.
   */
  protected function isAjaxMappingOperation(
    FormStateInterface $form_state,
  ): bool {
    $triggeringElement = $form_state->getTriggeringElement();
    $name = $triggeringElement['#name'] ?? '';

    return str_contains($name, 'add_mapping')
      || str_contains($name, 'remove_mapping');
  }

  /**
   * Validates that at least one field mapping is marked as an identifier.
   *
   * Skips validation when no non-empty mappings exist (entity_field must be
   * filled for a mapping to be considered non-empty).
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function validateIdentifierRequirement(FormStateInterface $form_state): void {
    $rawMappings = $form_state->getValue('field_mappings') ?? [];
    $nonEmptyMappings = $this->filterNonEmptyMappings($rawMappings);

    if (empty($nonEmptyMappings)) {
      return;
    }

    $identifierCount = count(array_filter(
      $nonEmptyMappings,
      static fn (array $row): bool => !empty($row['is_identifier']),
    ));

    if ($identifierCount === 0) {
      $form_state->setErrorByName(
        'field_mappings',
        $this->t('At least one field mapping must be marked as an identifier.'),
      );
    }
  }

  /**
   * Runs the verification plugin's configuration form validation if selected.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function validatePluginConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $selectedPlugin = $this->resolveSelectedVerificationPlugin($form_state);

    if (
      $selectedPlugin === NULL
      || $selectedPlugin === ''
      || !isset($form['verification']['verification_config'])
    ) {
      return;
    }

    $config = $this->resolveVerificationConfig($form_state);
    $plugin = $this->verificationManager->createInstance($selectedPlugin, $config);

    if ($plugin instanceof PluginFormInterface) {
      $subform = ['#parents' => ['verification_config']];
      $subformState = SubformState::createForSubform(
        $subform,
        $form,
        $form_state,
      );
      $plugin->validateConfigurationForm(
        $form['verification']['verification_config'],
        $subformState,
      );
    }
  }

  /**
   * Runs the verification plugin's submit handler and updates form state.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function submitPluginConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $selectedPlugin = $this->resolveSelectedVerificationPlugin($form_state);

    if (
      $selectedPlugin === NULL
      || $selectedPlugin === ''
      || !isset($form['verification']['verification_config'])
    ) {
      $form_state->setValue('verification_config', []);

      return;
    }

    $config = $this->resolveVerificationConfig($form_state);
    $plugin = $this->verificationManager->createInstance($selectedPlugin, $config);

    if ($plugin instanceof WebhookVerificationInterface) {
      $subform = ['#parents' => ['verification_config']];
      $subformState = SubformState::createForSubform(
        $subform,
        $form,
        $form_state,
      );
      $plugin->submitConfigurationForm($subform, $subformState);

      $form_state->setValue(
        ['verification_config'],
        $plugin->getConfiguration(),
      );
    }
  }

  /**
   * Validates each field mapping row's mutation plugin configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function validateMutationPluginForms(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $rawMappings = $form_state->getValue('field_mappings') ?? [];

    foreach ($rawMappings as $delta => $row) {
      if (!is_array($row)) {
        continue;
      }

      $pluginId = trim((string) ($row['mutation_plugin'] ?? ''));

      if ($pluginId === '' || !isset($form['field_mappings'][$delta]['mutation_config'])) {
        continue;
      }

      $config = (array) ($row['mutation_config'] ?? []);
      $plugin = $this->mutationManager->createInstance($pluginId, $config);

      if ($plugin instanceof PluginFormInterface) {
        $subform = &$form['field_mappings'][$delta]['mutation_config'];
        $subform['#parents'] = ['field_mappings', $delta, 'mutation_config'];
        $subformState = SubformState::createForSubform($subform, $form, $form_state);
        $plugin->validateConfigurationForm($subform, $subformState);
      }
    }
  }

  /**
   * Runs each field mapping row's mutation plugin submit handler.
   *
   * Updates form state values with the processed plugin configuration so that
   * applyFieldMappings() can persist them to the entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function submitMutationPluginForms(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $rawMappings = $form_state->getValue('field_mappings') ?? [];

    foreach ($rawMappings as $delta => $row) {
      if (!is_array($row)) {
        continue;
      }

      $pluginId = trim((string) ($row['mutation_plugin'] ?? ''));

      if ($pluginId === '' || !isset($form['field_mappings'][$delta]['mutation_config'])) {
        continue;
      }

      $config = (array) ($row['mutation_config'] ?? []);
      $plugin = $this->mutationManager->createInstance($pluginId, $config);

      if ($plugin instanceof FieldValueMutationInterface) {
        $subform = &$form['field_mappings'][$delta]['mutation_config'];
        $subform['#parents'] = ['field_mappings', $delta, 'mutation_config'];
        $subformState = SubformState::createForSubform($subform, $form, $form_state);
        $plugin->submitConfigurationForm($subform, $subformState);

        $form_state->setValue(
          ['field_mappings', $delta, 'mutation_config'],
          $plugin->getConfiguration(),
        );
      }
    }
  }

  /**
   * Resolves the verification config from form state or entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The verification configuration array.
   */
  protected function resolveVerificationConfig(FormStateInterface $form_state): array {
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = $this->entity;

    $formValue = $form_state->getValue(['verification_config']);

    if (is_array($formValue) && !empty($formValue)) {
      return $formValue;
    }

    return $entity->getVerificationConfig();
  }

  /**
   * Applies the verification plugin configuration to the entity.
   *
   * Reads the processed configuration from form state (set by
   * submitPluginConfigurationForm) and stores it on the entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function applyVerificationConfig(FormStateInterface $form_state): void {
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = $this->entity;

    $config = $form_state->getValue(['verification_config']) ?? [];
    $entity->set('verification_config', $config);
  }

  /**
   * Returns only mappings with a non-empty entity_field or json_path value.
   *
   * @param array<int|string, mixed> $rawMappings
   *   The raw form values for field_mappings.
   *
   * @return array<int, array<string, mixed>>
   *   Indexed array of non-empty mapping rows.
   */
  protected function filterNonEmptyMappings(array $rawMappings): array {
    $result = [];
    foreach ($rawMappings as $row) {
      if (!is_array($row)) {
        continue;
      }
      $entityField = trim((string) ($row['entity_field'] ?? ''));
      $jsonPath = trim((string) ($row['json_path'] ?? ''));
      if ($entityField !== '' || $jsonPath !== '') {
        $result[] = $row;
      }
    }

    return $result;
  }

  /**
   * Builds a single field mapping row element.
   *
   * @param int $delta
   *   The row index.
   * @param array<string, mixed> $defaults
   *   Default values for the row.
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null $endpoint
   *   The parent endpoint, if available.
   *
   * @return array<string, mixed>
   *   The form element array for one row.
   */
  protected function buildFieldMappingRow(
    int $delta,
    array $defaults,
    ?WebhookEndpointInterface $endpoint,
  ): array {
    $entityFieldElement = $this->buildEntityFieldElement($defaults, $endpoint);

    $row = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mapping @num', ['@num' => $delta + 1]),
      'entity_field' => $entityFieldElement,
      'json_path' => [
        '#type' => 'textfield',
        '#title' => $this->t('JSONPath Expression'),
        '#default_value' => $defaults['json_path'] ?? '',
        '#required' => FALSE,
      ],
      'is_identifier' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use as identifier'),
        '#default_value' => $defaults['is_identifier'] ?? FALSE,
      ],
      'mutation_plugin' => [
        '#type' => 'select',
        '#title' => $this->t('Mutation'),
        '#options' => $this->mutationManager->getOptions(),
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $defaults['mutation_plugin'] ?? '',
        '#ajax' => [
          'callback' => [$this, 'ajaxUpdateMutationConfig'],
          'wrapper' => 'mutation-config-wrapper-' . $delta,
        ],
      ],
      'mutation_config' => [
        '#type' => 'container',
        '#prefix' => '<div id="mutation-config-wrapper-' . $delta . '">',
        '#suffix' => '</div>',
      ],
    ];

    $hasValues = !empty(trim($defaults['entity_field'] ?? ''))
      || !empty(trim($defaults['json_path'] ?? ''));

    if ($hasValues) {
      $row['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_mapping_' . $delta,
        '#submit' => [[$this, 'removeFieldMappingRow']],
        '#ajax' => [
          'wrapper' => 'field-mappings-wrapper',
          'callback' => [$this, 'fieldMappingsCallback'],
        ],
        '#limit_validation_errors' => [],
      ];
    }

    return $row;
  }

  /**
   * Appends the mutation plugin configuration subform to a mapping row.
   *
   * Resolves the selected mutation plugin from form state and — when one is
   * selected — creates a plugin instance and merges its buildConfigurationForm
   * output into the row's mutation_config container.
   *
   * Must be called after the row is added to $form['field_mappings'][$delta]
   * so that the complete form array can be passed to SubformState.
   *
   * @param array<string, mixed> $row
   *   The field mapping row element, modified in place.
   * @param array $form
   *   The complete form array, used to construct SubformState correctly.
   * @param int $delta
   *   The row index.
   * @param array<string, mixed> $defaults
   *   Default values for this row.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function buildMutationConfigSubform(
    array &$row,
    array $form,
    int $delta,
    array $defaults,
    FormStateInterface $form_state,
  ): void {
    $defaultPluginId = (string) ($defaults['mutation_plugin'] ?? '');
    $selectedMutationPlugin = $this->resolveSelectedMutationPlugin($delta, $form_state, $defaultPluginId);

    if ($selectedMutationPlugin === NULL || $selectedMutationPlugin === '') {
      return;
    }

    $existingMutationConfig = (array) ($defaults['mutation_config'] ?? []);
    $plugin = $this->mutationManager->createInstance($selectedMutationPlugin, $existingMutationConfig);

    if (!($plugin instanceof PluginFormInterface)) {
      return;
    }

    $subform = &$row['mutation_config'];
    $subform['#parents'] = ['field_mappings', $delta, 'mutation_config'];
    $subformState = SubformState::createForSubform($subform, $form, $form_state);
    $row['mutation_config'] += $plugin->buildConfigurationForm($subform, $subformState);
  }

  /**
   * Builds the entity_field form element, using a select when endpoint is
   * available.
   *
   * @param array<string, mixed> $defaults
   *   Default values for this row.
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null $endpoint
   *   The parent endpoint, if available.
   *
   * @return array<string, mixed>
   *   The entity_field form element.
   */
  protected function buildEntityFieldElement(array $defaults, ?WebhookEndpointInterface $endpoint): array {
    $defaultValue = $defaults['entity_field'] ?? '';

    return [
      '#type' => 'select',
      '#title' => $this->t('Entity Field'),
      '#options' => $this->getEntityFieldOptions($endpoint),
      '#default_value' => $defaultValue,
      '#empty_option' => $this->t('- Select -'),
      '#empty_value' => '',
      '#required' => FALSE,
    ];
  }

  /**
   * Returns an options array of field names to labels for the endpoint's
   * entity type/bundle.
   *
   * Returns an empty array when no endpoint is provided, resulting in an empty
   * select element.
   *
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null $endpoint
   *   The webhook endpoint, or NULL if none is available.
   *
   * @return array<string, string>
   *   Keyed by field name, valued by field label string.
   */
  protected function getEntityFieldOptions(
    ?WebhookEndpointInterface $endpoint,
  ): array {
    if ($endpoint === NULL) {
      return [];
    }

    $bundle = $endpoint->getTargetEntityBundle();
    $entityTypeId = $endpoint->getTargetEntityTypeId();

    if ($bundle === '') {
      $bundle = $entityTypeId;
    }
    $options = [];
    $fieldDefinitions = $this->entityFieldManager
      ->getFieldDefinitions($entityTypeId, $bundle);

    foreach ($fieldDefinitions as $fieldName => $definition) {
      $options[$fieldName] = (string) $definition->getLabel();
    }
    asort($options);

    return $options;
  }

  /**
   * Resolves default values for a field mapping row considering form rebuilds.
   *
   * During AJAX rebuilds, user input must be preferred over stored entity data
   * so that in-progress edits are not lost.
   *
   * @param int $delta
   *   The row index.
   * @param array<int, array<string, mixed>> $existingMappings
   *   The entity's stored field mappings.
   * @param array<int, array<string, mixed>>|null $inputMappings
   *   Raw field mapping user input, or NULL if not an AJAX rebuild.
   *
   * @return array<string, mixed>
   *   The resolved defaults for this row.
   */
  protected function resolveFieldMappingDefaults(
    int $delta,
    array $existingMappings,
    ?array $inputMappings,
  ): array {
    if ($inputMappings !== NULL && isset($inputMappings[$delta])) {
      return $inputMappings[$delta];
    }

    return $existingMappings[$delta] ?? [];
  }

  /**
   * Parses the row delta from a remove button element name.
   *
   * The name format is 'remove_mapping_{delta}'.
   *
   * @param string $name
   *   The triggering element name.
   *
   * @return int
   *   The parsed delta.
   */
  protected function parseDeltaFromElementName(string $name): int {
    $parts = explode('_', $name);

    return (int) end($parts);
  }

  /**
   * Applies cleaned field mappings from form state to the entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function applyFieldMappings(FormStateInterface $form_state): void {
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceType $entity */
    $entity = $this->entity;

    $rawMappings = $form_state->getValue('field_mappings') ?? [];
    $cleanMappings = [];

    foreach ($rawMappings as $row) {
      if (!is_array($row)) {
        continue;
      }
      $jsonPath = trim((string) ($row['json_path'] ?? ''));
      $entityField = trim((string) ($row['entity_field'] ?? ''));

      if ($entityField === '' && $jsonPath === '') {
        continue;
      }
      $cleanMappings[] = [
        'entity_field' => $entityField,
        'json_path' => $jsonPath,
        'is_identifier' => (bool) ($row['is_identifier'] ?? FALSE),
        'mutation_plugin' => trim((string) ($row['mutation_plugin'] ?? '')),
        'mutation_config' => (array) ($row['mutation_config'] ?? []),
      ];
    }

    $entity->set('field_mappings', $cleanMappings);
    $entity->set(
      'verification_plugin',
      trim((string) ($form_state->getValue('verification_plugin') ?? '')),
    );
  }
}
