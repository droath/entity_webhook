<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface;
use Drupal\entity_webhook\Traits\AjaxFormStateTrait;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the add/edit form for OutboundFieldMapping config entities.
 */
class OutboundFieldMappingForm extends EntityForm {

  use AjaxFormStateTrait;

  /**
   * The outbound value resolver plugin manager.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected OutboundValueResolverManagerInterface $resolverManager;

  /**
   * The field value mutation plugin manager.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected FieldValueMutationManagerInterface $mutationManager;

  /**
   * Constructs an OutboundFieldMappingForm.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManagerInterface $resolverManager
   *   The outbound value resolver plugin manager.
   * @param \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $mutationManager
   *   The field value mutation plugin manager.
   */
  public function __construct(
    RouteMatchInterface $routeMatch,
    OutboundValueResolverManagerInterface $resolverManager,
    FieldValueMutationManagerInterface $mutationManager,
  ) {
    $this->routeMatch = $routeMatch;
    $this->resolverManager = $resolverManager;
    $this->mutationManager = $mutationManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('plugin.manager.outbound_value_resolver'),
      $container->get('plugin.manager.field_value_mutation'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $entity */
    $entity = $this->entity;

    $subscription = $this->resolveSubscription();
    $subscriptionId = $subscription?->id() ?? '';

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->resolveRawId($entity->id() ?? '', $subscriptionId),
      '#machine_name' => [
        'exists' => '\Drupal\entity_webhook_broadcast\Entity\OutboundFieldMapping::load',
        'replace_pattern' => '[^a-z0-9_.]+',
        'source' => ['label'],
      ],
      '#disabled' => !$entity->isNew(),
      '#field_prefix' => $subscriptionId !== '' ? $subscriptionId . '.' : '',
      '#description' => $this->t(
        'A unique machine name. The prefix <em>@prefix.</em> will be added automatically.',
        ['@prefix' => $subscriptionId],
      ),
    ];

    $form['resolver'] = [
      '#type' => 'select',
      '#title' => $this->t('Value Resolver'),
      '#description' => $this->t('The plugin that produces the value for this field from the entity.'),
      '#options' => $this->resolverManager->getOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#empty_value' => '',
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

    $form['output_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output Key'),
      '#description' => $this->t('The JSON key name in the outbound webhook payload.'),
      '#default_value' => $entity->getOutputKey(),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#pattern' => '[a-zA-Z0-9_]+',
    ];

    $form['mutation_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Value Mutation'),
      '#description' => $this->t('Optional plugin to transform the field value before including it in the payload.'),
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
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $entity */
    $entity = $this->entity;

    $subscription = $this->resolveSubscription();
    $subscriptionId = $subscription?->id() ?? '';

    if ($entity->isNew() && $subscriptionId !== '') {
      $rawId = $form_state->getValue('id');
      $entity->set('id', $subscriptionId . '.' . $rawId);
      $entity->set('subscription_id', $subscriptionId);
    }

    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Field mapping %label has been created.', ['%label' => $entity->label()])
        : $this->t('Field mapping %label has been updated.', ['%label' => $entity->label()]),
    );

    $form_state->setRedirectUrl($entity->toUrl('collection'));

    return $status;
  }

  /**
   * Resolves the parent OutboundSubscription from the current route parameter.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null
   *   The subscription entity, or NULL if not in the route.
   */
  protected function resolveSubscription(): ?OutboundSubscriptionInterface {
    $subscription = $this->routeMatch->getParameter('outbound_subscription');

    return $subscription instanceof OutboundSubscriptionInterface ? $subscription : NULL;
  }

  /**
   * Resolves the parent OutboundEndpoint from the current route parameter.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null
   *   The endpoint entity, or NULL if not in the route.
   */
  protected function resolveEndpoint(): ?OutboundEndpointInterface {
    $endpoint = $this->routeMatch->getParameter('outbound_endpoint');

    return $endpoint instanceof OutboundEndpointInterface ? $endpoint : NULL;
  }

  /**
   * Returns the raw machine name portion of a composite entity ID.
   *
   * @param string $fullId
   *   The full composite ID (e.g. 'subscription_id.field_name').
   * @param string $prefix
   *   The prefix to strip (e.g. 'subscription_id').
   *
   * @return string
   *   The raw machine name without the prefix.
   */
  protected function resolveRawId(string $fullId, string $prefix): string {
    if ($prefix !== '' && str_starts_with($fullId, $prefix . '.')) {
      return substr($fullId, strlen($prefix) + 1);
    }

    return $fullId;
  }

  /**
   * Resolves the selected resolver plugin ID across all form lifecycle phases.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return string
   *   The plugin ID, or empty string when none is selected.
   */
  protected function resolveSelectedResolverPlugin(FormStateInterface $form_state): string {
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $entity */
    $entity = $this->entity;

    return (string) $this->getFormStateValue(
      'resolver',
      $form_state,
      $entity->getResolver(),
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
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $entity */
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
   * Sets the outbound_resolver_context form state key so that
   * EntityFieldResolver::buildConfigurationForm() can access entity type info.
   *
   * @param array<string, mixed> $form
   *   The complete form array, modified in place.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function buildResolverConfigSubform(array &$form, FormStateInterface $form_state): void {
    $pluginId = $this->resolveSelectedResolverPlugin($form_state);

    if ($pluginId === '') {
      return;
    }

    $this->setResolverContext($form_state);

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $entity */
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
   * Validates the resolver plugin configuration form if one is active.
   *
   * @param array<string, mixed> $form
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
   * Runs the resolver plugin submit handler and updates form state values.
   *
   * @param array<string, mixed> $form
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

    if ($plugin instanceof PluginFormInterface) {
      $subform = &$form['resolver_config'];
      $subform['#parents'] = ['resolver_config'];
      $subformState = SubformState::createForSubform($subform, $form, $form_state);
      $plugin->submitConfigurationForm($subform, $subformState);
      $form_state->setValue('resolver_config', $plugin->getConfiguration());
    }
  }

  /**
   * Builds the mutation plugin configuration subform into the form array.
   *
   * @param array<string, mixed> $form
   *   The complete form array, modified in place.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function buildMutationConfigSubform(array &$form, FormStateInterface $form_state): void {
    $pluginId = $this->resolveSelectedMutationPlugin($form_state);

    if ($pluginId === '') {
      return;
    }

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface $entity */
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
   * Validates the mutation plugin configuration form if one is active.
   *
   * @param array<string, mixed> $form
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
   * Runs the mutation plugin submit handler and updates form state values.
   *
   * @param array<string, mixed> $form
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

  /**
   * Sets endpoint entity type and bundle context in form state for resolver
   * plugins that need it (e.g. EntityFieldResolver).
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  private function setResolverContext(FormStateInterface $form_state): void {
    $endpoint = $this->resolveEndpoint();

    if ($endpoint === NULL) {
      return;
    }

    $form_state->set('outbound_resolver_context', [
      'entity_type_id' => $endpoint->getWatchedEntityType(),
      'bundle' => $endpoint->getEntityBundle() ?? '',
    ]);
  }

}
