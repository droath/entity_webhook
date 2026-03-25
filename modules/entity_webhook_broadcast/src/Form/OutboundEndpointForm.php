<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Form;

use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\entity_webhook\Traits\AjaxFormStateTrait;
use Drupal\entity_webhook\Traits\ConfigEntityFormTrait;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the add/edit form for OutboundEndpoint config entities.
 */
class OutboundEndpointForm extends EntityForm {
  use AjaxFormStateTrait;
  use ConfigEntityFormTrait;

  /**
   * Constructs an OutboundEndpointForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Executable\ExecutableManagerInterface $conditionManager
   *   The condition plugin manager.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected ExecutableManagerInterface $conditionManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.condition'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $entity */
    $entity = $this->entity;

    $form['#prefix'] = '<div id="outbound-endpoint-form">';
    $form['#suffix'] = '</div>';

    $form += $this->buildLabelIdElements(
      '\Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint::load',
      $entity,
    );

    $selectedEntityType = (string) $this->getFormStateValue(
      'entity_type',
      $form_state,
      $entity->getWatchedEntityType(),
    );

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $this->getContentEntityTypeOptions(),
      '#default_value' => $selectedEntityType,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#ajax' => [
        'method' => 'replaceWith',
        'wrapper' => 'outbound-endpoint-form',
        'callback' => [$this, 'ajaxRebuildForm'],
      ],
    ];

    if ($selectedEntityType !== '') {
      $bundleOptions = $this->getBundleOptions($selectedEntityType);
      $selectedBundle = (string) $this->getFormStateValue(
        'entity_bundle',
        $form_state,
        $entity->getEntityBundle() ?? '',
      );
      $form['entity_bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity Bundle'),
        '#description' => $this->t('Optionally limit to a specific bundle. Leave empty to match all bundles.'),
        '#options' => $bundleOptions,
        '#empty_option' => $this->t('- All bundles -'),
        '#empty_value' => '',
        '#default_value' => $selectedBundle,
      ];
    }

    $form['events'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Events'),
      '#description' => $this->t('Select the entity lifecycle events that will trigger dispatch.'),
      '#options' => [
        'insert' => $this->t('Insert (entity created)'),
        'update' => $this->t('Update (entity saved)'),
        'delete' => $this->t('Delete (entity deleted)'),
      ],
      '#default_value' => $entity->getEvents(),
      '#required' => TRUE,
    ];

    $form['conditions'] = $this->buildConditionsSection($form_state);

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('When disabled, no webhooks are dispatched for this endpoint.'),
      '#default_value' => $entity->isEnabled(),
    ];

    return $form;
  }

  /**
   * AJAX callback that returns the entire form for a full rebuild.
   *
   * @param array<string, mixed> $form
   *   The form array.
   *
   * @return array<string, mixed>
   *   The entire form element.
   */
  public function ajaxRebuildForm(array $form): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $this->validateConditionsSection($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $events = array_keys(array_filter((array) $form_state->getValue('events')));
    $form_state->setValue('events', $events);

    $this->submitConditionsSection($form, $form_state);

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $entity */
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Endpoint %label has been created.', ['%label' => $entity->label()])
        : $this->t('Endpoint %label has been updated.', ['%label' => $entity->label()]),
    );

    $form_state->setRedirectUrl($entity->toUrl('collection'));

    return $status;
  }

  /**
   * Builds the conditions section of the form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<string, mixed>
   *   The conditions form section.
   */
  protected function buildConditionsSection(FormStateInterface $form_state): array {
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $entity */
    $entity = $this->entity;

    $section = [
      '#type' => 'details',
      '#title' => $this->t('Conditions'),
      '#description' => $this->t('All active conditions must pass for webhooks to be dispatched. Leave all conditions unconfigured to always dispatch.'),
      '#open' => $entity->getActiveConditions() !== [],
      '#tree' => TRUE,
    ];

    $selectedEntityType = (string) $this->getFormStateValue(
      'entity_type',
      $form_state,
      $entity->getWatchedEntityType(),
    );

    if ($selectedEntityType === '') {
      return $section;
    }

    $activeConditions = $entity->getActiveConditions();
    $existingConditions = $entity->getConditions()->getConfiguration();
    $definitions = $this->getApplicableConditionDefinitions($selectedEntityType);

    foreach ($definitions as $conditionId => $definition) {
      $config = $existingConditions[$conditionId] ?? [];

      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionManager->createInstance($conditionId, $config);
      $form_state->set(['conditions', $conditionId], $condition);

      $conditionForm = ['#parents' => ['conditions', $conditionId]];
      $conditionForm = $condition->buildConfigurationForm(
        $conditionForm,
        SubformState::createForSubform($conditionForm, $section, $form_state),
      );
      $conditionForm['#type'] = 'details';
      $conditionForm['#title'] = $definition['label'];
      $conditionForm['#open'] = isset($activeConditions[$conditionId]);

      $section[$conditionId] = $conditionForm;
    }

    return $section;
  }

  /**
   * Returns condition definitions applicable to the given entity type.
   *
   * Keeps conditions that declare a generic entity context (keyed 'entity'),
   * plus the entity_bundle derivative that matches the selected entity type.
   * All other entity_bundle derivatives are excluded.
   *
   * @param string $entityTypeId
   *   The selected entity type machine name.
   *
   * @return array<string, mixed>
   *   Filtered condition definitions keyed by plugin ID.
   */
  protected function getApplicableConditionDefinitions(string $entityTypeId): array {
    $definitions = $this->conditionManager->getDefinitions();

    return array_filter(
      $definitions,
      static function (array $definition, string $conditionId) use ($entityTypeId): bool {
        $contextDefinitions = $definition['context_definitions'] ?? [];

        if (str_starts_with($conditionId, 'entity_bundle:')) {
          return $conditionId === 'entity_bundle:' . $entityTypeId;
        }

        return isset($contextDefinitions['entity']);
      },
      ARRAY_FILTER_USE_BOTH,
    );
  }

  /**
   * Validates all condition plugin subforms.
   *
   * @param array<string, mixed> $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function validateConditionsSection(array $form, FormStateInterface $form_state): void {
    foreach ((array) $form_state->getValue('conditions') as $conditionId => $values) {
      $condition = $form_state->get(['conditions', $conditionId]);
      if (!$condition instanceof ConditionInterface) {
        continue;
      }

      $conditionForm = $form['conditions'][$conditionId];
      $condition->validateConfigurationForm(
        $conditionForm,
        SubformState::createForSubform($conditionForm, $form, $form_state),
      );
    }
  }

  /**
   * Submits condition plugin subforms and writes canonical config to form state.
   *
   * Calls submitConfigurationForm() on each condition plugin and replaces the
   * raw form values with the plugin's canonical configuration. The standard
   * config entity form handling in parent::submitForm() then maps the values
   * from form state onto the entity.
   *
   * @param array<string, mixed> $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function submitConditionsSection(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $entity */
    $entity = $this->entity;

    if ($conditions = $form_state->getValue('conditions')) {
      foreach ($conditions as $conditionId => $values) {
        $condition = $form_state->get(['conditions', $conditionId]);
        if (!$condition instanceof ConditionInterface) {
          continue;
        }

        $conditionForm = $form['conditions'][$conditionId];
        $condition->submitConfigurationForm(
          $conditionForm,
          SubformState::createForSubform($conditionForm, $form, $form_state),
        );

        // Update the entity's plugin collection directly. EntityForm skips
        // keys returned by getPluginCollections() in copyFormValuesToEntity(),
        // so form state values alone are insufficient.
        $entity->getConditions()->addInstanceId($conditionId, $condition->getConfiguration());
      }
    }
  }

  /**
   * Returns an options array of content entity type IDs to labels.
   *
   * @return array<string, string>
   *   Keyed by entity type ID, valued by label.
   */
  protected function getContentEntityTypeOptions(): array {
    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
      if ($definition->getGroup() === 'content') {
        $options[$id] = (string) $definition->getLabel();
      }
    }
    asort($options);

    return $options;
  }

  /**
   * Returns an options array of bundle IDs to labels for a given entity type.
   *
   * @param string $entityTypeId
   *   The entity type machine name.
   *
   * @return array<string, string>
   *   Keyed by bundle ID, valued by label string.
   */
  protected function getBundleOptions(string $entityTypeId): array {
    return array_map(
      static fn($bundleData) => (string) $bundleData['label'],
      $this->bundleInfo->getBundleInfo($entityTypeId),
    );
  }

}
