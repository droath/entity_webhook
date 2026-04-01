<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Form;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity_webhook\Traits\AjaxFormStateTrait;
use Drupal\entity_webhook\Traits\ConfigEntityFormTrait;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationInterface;
use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface;

/**
 * Provides the add/edit form for WebhookSourceType config entities.
 *
 * Handles label, machine name, and verification plugin configuration only.
 * Field mappings are managed separately via WebhookFieldMappingForm.
 */
class WebhookSourceTypeForm extends EntityForm {
  use AjaxFormStateTrait;
  use ConfigEntityFormTrait;

  /**
   * The webhook verification plugin manager.
   *
   * Not readonly because DependencySerializationTrait::__wakeup() must
   * re-inject this property after the form is unserialized during AJAX.
   */
  protected WebhookVerificationManagerInterface $verificationManager;

  /**
   * Constructs a WebhookSourceTypeForm.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface $verificationManager
   *   The webhook verification plugin manager.
   */
  public function __construct(
    RouteMatchInterface $routeMatch,
    WebhookVerificationManagerInterface $verificationManager,
  ) {
    $this->routeMatch = $routeMatch;
    $this->verificationManager = $verificationManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('plugin.manager.webhook_verification'),
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

    $form['operation'] = [
      '#type' => 'select',
      '#title' => $this->t('Operation'),
      '#options' => [
        'upsert' => $this->t('Upsert (Create or Update)'),
        'delete' => $this->t('Delete'),
      ],
      '#default_value' => $entity->getOperation(),
      '#description' => $this->t('The entity operation to perform when a webhook payload is received.'),
      '#required' => TRUE,
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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $this->validatePluginConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->submitPluginConfigurationForm($form, $form_state);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = $this->entity;

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

    $plugin = trim((string) ($form_state->getValue('verification_plugin') ?? ''));
    $entity->set('verification_plugin', $plugin);

    $config = $form_state->getValue(['verification_config']) ?? [];
    $entity->set('verification_config', $config);
  }
}
