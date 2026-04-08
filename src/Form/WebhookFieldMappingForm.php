<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Form;

use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\entity_webhook\Entity\WebhookFieldMapping;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\entity_webhook\Traits\AjaxFormStateTrait;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverInterface;
use Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverManagerInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;

/**
 * Provides the add/edit form for WebhookFieldMapping config entities.
 */
class WebhookFieldMappingForm extends EntityForm {
  use AjaxFormStateTrait;

  /**
   * The entity field manager service.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The value resolver plugin manager.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected ValueResolverManagerInterface $resolverManager;

  /**
   * The field value mutation plugin manager.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected FieldValueMutationManagerInterface $mutationManager;

  /**
   * Constructs a WebhookFieldMappingForm.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverManagerInterface $resolverManager
   *   The value resolver plugin manager.
   * @param \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $mutationManager
   *   The field value mutation plugin manager.
   */
  public function __construct(
    RouteMatchInterface $routeMatch,
    EntityFieldManagerInterface $entityFieldManager,
    ValueResolverManagerInterface $resolverManager,
    FieldValueMutationManagerInterface $mutationManager,
  ) {
    $this->routeMatch = $routeMatch;
    $this->entityFieldManager = $entityFieldManager;
    $this->resolverManager = $resolverManager;
    $this->mutationManager = $mutationManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.value_resolver'),
      $container->get('plugin.manager.field_value_mutation'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $entity */
    $entity = $this->entity;

    $sourceType = $this->resolveSourceType();
    $sourceTypeId = $sourceType?->id() ?? '';

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->resolveRawId($entity->id(), $sourceTypeId),
      '#machine_name' => [
        'exists' => [static::class, 'fieldMappingExists'],
        'replace_pattern' => '[^a-z0-9_.]+',
        'source' => ['label'],
      ],
      '#disabled' => !$entity->isNew(),
      '#field_prefix' => $sourceTypeId !== '' ? $sourceTypeId . '.' : '',
      '#description' => $this->t('A unique machine name for this mapping. The prefix <em>@prefix.</em> will be added automatically.', ['@prefix' => $sourceTypeId]),
    ];

    $endpoint = $this->resolveEndpoint();

    $form['entity_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Field'),
      '#options' => $this->getEntityFieldOptions($endpoint),
      '#default_value' => $entity->getEntityField(),
      '#empty_option' => $this->t('- Select -'),
      '#empty_value' => '',
      '#required' => TRUE,
    ];

    $form['is_identifier'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use as identifier'),
      '#default_value' => $entity->isIdentifier(),
      '#description' => $this->t('Mark this field as a lookup key for entity upsert operations.'),
    ];

    $form['resolver'] = [
      '#type' => 'select',
      '#title' => $this->t('Value Resolver'),
      '#options' => $this->resolverManager->getOptions(),
      '#default_value' => $entity->getResolver(),
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => 'resolver-config-wrapper',
        'callback' => [$this, 'ajaxUpdateResolverConfig'],
      ],
    ];

    $form['resolver_config'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="resolver-config-wrapper">',
      '#suffix' => '</div>',
    ];

    $this->buildResolverConfigSubform($form, $form_state);

    $form['mutation_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Mutation'),
      '#options' => $this->mutationManager->getOptions(),
      '#empty_option' => $this->t('- None -'),
      '#empty_value' => '',
      '#default_value' => $entity->getMutationPlugin(),
      '#ajax' => [
        'wrapper' => 'mutation-config-wrapper',
        'callback' => [$this, 'ajaxUpdateMutationConfig'],
      ],
    ];

    $form['mutation_config'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="mutation-config-wrapper">',
      '#suffix' => '</div>',
    ];

    $this->buildMutationConfigSubform($form, $form_state);

    return $form;
  }

  /**
   * AJAX callback that returns the resolver_config container element.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The resolver config container element.
   */
  public function ajaxUpdateResolverConfig(array &$form, FormStateInterface $form_state): array {
    return $form['resolver_config'];
  }

  /**
   * AJAX callback that returns the mutation_config container element.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The mutation config container element.
   */
  public function ajaxUpdateMutationConfig(array &$form, FormStateInterface $form_state): array {
    return $form['mutation_config'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $sourceType = $this->resolveSourceType();
    $sourceTypeId = $sourceType?->id() ?? '';

    if ($this->entity->isNew() && $sourceTypeId !== '') {
      $rawId = $form_state->getValue('id');
      $fullId = $sourceTypeId . '.' . $rawId;
      $form_state->setValueForElement($form['id'], $fullId);
    }

    $this->validateResolverPluginForm($form, $form_state);
    $this->validateMutationPluginForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->submitResolverPluginForm($form, $form_state);
    $this->submitMutationPluginForm($form, $form_state);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $entity */
    $entity = $this->entity;

    $sourceType = $this->resolveSourceType();
    $sourceTypeId = $sourceType?->id() ?? '';

    if ($entity->isNew() && $sourceTypeId !== '') {
      $entity->set('source_type', $sourceTypeId);
    }
    elseif (!$entity->isNew()) {
      $entity->set('id', $entity->getOriginalId());
    }

    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Field mapping %label has been created.', ['%label' => $entity->label()])
        : $this->t('Field mapping %label has been updated.', ['%label' => $entity->label()]),
    );

    $endpoint = $this->resolveEndpoint();
    if ($endpoint !== NULL && $sourceType !== NULL) {
      $form_state->setRedirectUrl(Url::fromRoute('entity.webhook_field_mapping.collection', [
        'webhook_endpoint' => $endpoint->id(),
        'webhook_source_type' => $sourceTypeId,
      ]));
    }

    return $status;
  }

  /**
   * Checks whether a field mapping with the composite ID already exists.
   *
   * The #machine_name element passes only the raw value typed by the user.
   * This callback reconstructs the full composite ID by prepending the source
   * type prefix stored in #field_prefix before performing the existence check.
   *
   * @param string $value
   *   The raw machine name entered by the user (e.g. 'source').
   * @param array<string, mixed> $element
   *   The #machine_name form element, which carries #field_prefix.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return bool
   *   TRUE when an entity with the composite ID already exists.
   */
  public static function fieldMappingExists(string $value, array $element, FormStateInterface $form_state): bool {
    $prefix = rtrim((string) ($element['#field_prefix'] ?? ''), '.');
    $fullId = $prefix !== '' ? $prefix . '.' . $value : $value;

    return (bool) WebhookFieldMapping::load($fullId);
  }

  /**
   * Resolves the parent WebhookSourceType from the current route parameter.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface|null
   *   The resolved source type, or NULL if the route does not carry one.
   */
  protected function resolveSourceType(): ?WebhookSourceTypeInterface {
    $sourceType = $this->routeMatch->getParameter('webhook_source_type');

    return $sourceType instanceof
    WebhookSourceTypeInterface ? $sourceType
      : NULL;
  }

  /**
   * Resolves the parent WebhookEndpoint from the current route parameter.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null
   *   The resolved endpoint, or NULL if the route does not carry one.
   */
  protected function resolveEndpoint(): ?WebhookEndpointInterface {
    $endpoint = $this->routeMatch->getParameter('webhook_endpoint');

    return $endpoint instanceof WebhookEndpointInterface ? $endpoint : NULL;
  }

  /**
   * Returns only the machine name portion of a composite entity ID.
   *
   * @param string $fullId
   *   The full composite ID (e.g. 'source_type.field_name').
   * @param string $prefix
   *   The prefix to strip (e.g. 'source_type').
   *
   * @return string
   *   The raw machine name without the prefix, or the full ID if no prefix.
   */
  protected function resolveRawId(string $fullId, string $prefix): string {
    if ($prefix !== '' && str_starts_with($fullId, $prefix . '.')) {
      return substr($fullId, strlen($prefix) + 1);
    }

    return $fullId;
  }

  /**
   * Returns an options array of field names to labels for the endpoint entity.
   *
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null $endpoint
   *   The webhook endpoint, or NULL if none is available.
   *
   * @return array<string, string>
   *   Keyed by field name, valued by field label string.
   */
  protected function getEntityFieldOptions(?WebhookEndpointInterface $endpoint): array {
    if ($endpoint === NULL) {
      return [];
    }

    $entityTypeId = $endpoint->getTargetEntityTypeId();
    $bundle = $endpoint->getTargetEntityBundle();

    if ($bundle === '') {
      $bundle = $entityTypeId;
    }

    $options = [];
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle);

    foreach ($fieldDefinitions as $fieldName => $definition) {
      $options[$fieldName] = (string) $definition->getLabel();
    }
    asort($options);

    return $options;
  }

  /**
   * Resolves the selected resolver plugin ID across all form lifecycle phases.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return string
   *   The plugin ID, falling back to 'json_path'.
   */
  protected function resolveSelectedResolverPlugin(FormStateInterface $form_state): string {
    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $entity */
    $entity = $this->entity;

    return (string) $this->getFormStateValue(
      'resolver',
      $form_state,
      $entity->getResolver() ?: 'json_path',
    );
  }

  /**
   * Resolves the selected mutation plugin ID across all form lifecycle phases.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return string
   *   The plugin ID, or empty string when none is selected.
   */
  protected function resolveSelectedMutationPlugin(FormStateInterface $form_state): string {
    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $entity */
    $entity = $this->entity;

    return (string) $this->getFormStateValue(
      'mutation_plugin',
      $form_state,
      $entity->getMutationPlugin(),
    );
  }

  /**
   * Builds the resolver plugin configuration subform into the form array.
   *
   * @param array $form
   *   The complete form array, modified in place.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function buildResolverConfigSubform(array &$form, FormStateInterface $form_state): void {
    $pluginId = $this->resolveSelectedResolverPlugin($form_state);

    if ($pluginId === '') {
      return;
    }

    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $entity */
    $entity = $this->entity;
    $existingConfig = $entity->getResolverConfig();
    $plugin = $this->resolverManager->createInstance($pluginId, $existingConfig);

    if (!($plugin instanceof PluginFormInterface)) {
      return;
    }

    $subform = &$form['resolver_config'];
    $subform['#parents'] = ['resolver_config'];
    $subformState = SubformState::createForSubform($subform, $form, $form_state);
    $form['resolver_config'] += $plugin->buildConfigurationForm($subform, $subformState);
  }

  /**
   * Builds the mutation plugin configuration subform into the form array.
   *
   * @param array $form
   *   The complete form array, modified in place.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function buildMutationConfigSubform(array &$form, FormStateInterface $form_state): void {
    $pluginId = $this->resolveSelectedMutationPlugin($form_state);

    if ($pluginId === '') {
      return;
    }

    /** @var \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface $entity */
    $entity = $this->entity;
    $existingConfig = $entity->getMutationConfig();
    $plugin = $this->mutationManager->createInstance($pluginId, $existingConfig);

    if (!($plugin instanceof PluginFormInterface)) {
      return;
    }

    $subform = &$form['mutation_config'];
    $subform['#parents'] = ['mutation_config'];
    $subformState = SubformState::createForSubform($subform, $form, $form_state);
    $form['mutation_config'] += $plugin->buildConfigurationForm($subform, $subformState);
  }

  /**
   * Validates the resolver plugin configuration form if one is active.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function validateResolverPluginForm(array &$form, FormStateInterface $form_state): void {
    $pluginId = $this->resolveSelectedResolverPlugin($form_state);

    if ($pluginId === '' || !isset($form['resolver_config'])) {
      return;
    }

    $config = (array) ($form_state->getValue('resolver_config') ?? []);
    $plugin = $this->resolverManager->createInstance($pluginId, $config);

    if ($plugin instanceof PluginFormInterface) {
      $subform = &$form['resolver_config'];
      $subform['#parents'] = ['resolver_config'];
      $subformState = SubformState::createForSubform($subform, $form, $form_state);
      $plugin->validateConfigurationForm($subform, $subformState);
    }
  }

  /**
   * Validates the mutation plugin configuration form if one is active.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function validateMutationPluginForm(array &$form, FormStateInterface $form_state): void {
    $pluginId = $this->resolveSelectedMutationPlugin($form_state);

    if ($pluginId === '' || !isset($form['mutation_config'])) {
      return;
    }

    $config = (array) ($form_state->getValue('mutation_config') ?? []);
    $plugin = $this->mutationManager->createInstance($pluginId, $config);

    if ($plugin instanceof PluginFormInterface) {
      $subform = &$form['mutation_config'];
      $subform['#parents'] = ['mutation_config'];
      $subformState = SubformState::createForSubform($subform, $form, $form_state);
      $plugin->validateConfigurationForm($subform, $subformState);
    }
  }

  /**
   * Runs the resolver plugin submit handler and updates form state values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function submitResolverPluginForm(array &$form, FormStateInterface $form_state): void {
    $pluginId = $this->resolveSelectedResolverPlugin($form_state);

    if ($pluginId === '' || !isset($form['resolver_config'])) {
      $form_state->setValue('resolver_config', []);

      return;
    }

    $config = (array) ($form_state->getValue('resolver_config') ?? []);
    $plugin = $this->resolverManager->createInstance($pluginId, $config);

    if ($plugin instanceof ValueResolverInterface) {
      $subform = &$form['resolver_config'];
      $subform['#parents'] = ['resolver_config'];
      $subformState = SubformState::createForSubform($subform, $form, $form_state);
      $plugin->submitConfigurationForm($subform, $subformState);
      $form_state->setValue('resolver_config', $plugin->getConfiguration());
    }
  }

  /**
   * Runs the mutation plugin submit handler and updates form state values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function submitMutationPluginForm(array &$form, FormStateInterface $form_state): void {
    $pluginId = $this->resolveSelectedMutationPlugin($form_state);

    if ($pluginId === '' || !isset($form['mutation_config'])) {
      $form_state->setValue('mutation_config', []);

      return;
    }

    $config = (array) ($form_state->getValue('mutation_config') ?? []);
    $plugin = $this->mutationManager->createInstance($pluginId, $config);

    if ($plugin instanceof FieldValueMutationInterface) {
      $subform = &$form['mutation_config'];
      $subform['#parents'] = ['mutation_config'];
      $subformState = SubformState::createForSubform($subform, $form, $form_state);
      $plugin->submitConfigurationForm($subform, $subformState);
      $form_state->setValue('mutation_config', $plugin->getConfiguration());
    }
  }

}
