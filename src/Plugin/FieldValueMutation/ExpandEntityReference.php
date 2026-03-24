<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_webhook\Attribute\FieldValueMutation;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Expands entity reference fields into structured data using a view display.
 */
#[FieldValueMutation(
  id: 'expand_entity_reference',
  label: new TranslatableMarkup('Expand Entity Reference'),
  description: new TranslatableMarkup('Expands entity reference fields into structured data using a view display as the field selector.'),
)]
class ExpandEntityReference extends FieldValueMutationBase implements ContainerFactoryPluginInterface {
  /**
   * Constructs an ExpandEntityReference plugin.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function defaultConfiguration(): array {
    return ['target_type' => '', 'view_mode' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function mutate(mixed $value): mixed {
    if (is_int($value) || (is_string($value) && is_numeric($value))) {
      return $this->expandIds([(int) $value], isSingleValue: TRUE);
    }

    if (is_array($value)) {
      $ids = $this->extractIdsFromArray($value);
      if ($ids === NULL) {
        return $value;
      }

      return $this->expandIds($ids, isSingleValue: FALSE);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $currentTargetType = $this->resolveCurrentTargetType($form_state);

    $form['target_type'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Target Entity Type'),
      '#options' => $this->buildEntityTypeOptions(),
      '#default_value' => $this->configuration['target_type'],
      '#empty_option' => new TranslatableMarkup('- Select -'),
      '#empty_value' => '',
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => 'mutation-config-wrapper',
        'callback' => '::ajaxUpdateMutationConfig',
      ],
    ];

    $form['view_mode'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('View Mode'),
      '#options' => $this->buildViewModeOptions($currentTargetType),
      '#default_value' => $this->configuration['view_mode'],
      '#empty_option' => new TranslatableMarkup('- Select -'),
      '#empty_value' => '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['target_type'] = $form_state->getValue('target_type');
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
  }

  /**
   * Extracts entity IDs from an array of either scalar IDs or target_id arrays.
   *
   * Handles two formats produced by PayloadBuilder::extractFieldValue():
   * - Flat array of numeric scalars: ["1", "2"] or [1, 2]
   * - Array of target_id arrays: [['target_id' => 1], ['target_id' => 2]]
   *
   * Returns NULL when the array is empty or does not match either format.
   *
   * @param array<mixed> $value
   *   The multi-value field array.
   *
   * @return int[]|null
   *   The extracted IDs, or NULL if the format is not recognized.
   */
  private function extractIdsFromArray(array $value): ?array {
    if (empty($value)) {
      return NULL;
    }

    $ids = [];

    foreach ($value as $item) {
      if (is_int($item) || (is_string($item) && is_numeric($item))) {
        $ids[] = (int) $item;
      } elseif (is_array($item) && array_key_exists('target_id', $item)) {
        $ids[] = (int) $item['target_id'];
      } else {
        return NULL;
      }
    }

    return $ids;
  }

  /**
   * Expands an array of entity IDs into structured data.
   *
   * @param int[] $ids
   *   The entity IDs to expand.
   * @param bool $isSingleValue
   *   Whether the original input was a single ID (returns an object instead of
   *   an array).
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return mixed
   *   The expanded entity data.
   */
  private function expandIds(array $ids, bool $isSingleValue): mixed {
    $targetType = $this->configuration['target_type'];
    $storage = $this->entityTypeManager->getStorage($targetType);
    $entities = $storage->loadMultiple($ids);

    $results = [];

    foreach ($ids as $id) {
      $entity = $entities[$id] ?? NULL;
      $results[] = $entity instanceof ContentEntityInterface
        ? $this->expandEntity($entity, $id)
        : $id;
    }

    return $isSingleValue ? reset($results) : $results;
  }

  /**
   * Expands a single entity into a structured array using the configured
   * display.
   *
   * Returns the raw entity ID if no display components are found.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to expand.
   * @param int $id
   *   The original entity ID used as fallback when no display components
   *   exist.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return array<string, mixed>|int
   *   The structured field data, or the entity ID if no components exist.
   */
  private function expandEntity(ContentEntityInterface $entity, int $id): array|int {
    $display = $this->loadDisplay($entity);
    $components = $display?->getComponents() ?? [];

    if (empty($components)) {
      return $id;
    }

    $data = [];

    foreach (array_keys($components) as $fieldName) {
      $data[$fieldName] = $this->extractFieldValue($entity, (string) $fieldName);
    }

    return $data;
  }

  /**
   * Loads the entity view display for the given entity and configured view
   * mode.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to load the display for.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface|null
   *   The display, or NULL if not found.
   */
  private function loadDisplay(ContentEntityInterface $entity): ?EntityViewDisplayInterface {
    $displayStorage = $this->entityTypeManager->getStorage('entity_view_display');
    $viewMode = $this->configuration['view_mode'];
    $displayId = $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $viewMode;

    $display = $displayStorage->load($displayId);

    return $display instanceof EntityViewDisplayInterface ? $display : NULL;
  }

  /**
   * Extracts and unwraps a raw field value from an entity.
   *
   * Single-key arrays are unwrapped to their scalar value. Entity reference
   * fields are recursively expanded when a matching display exists.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity.
   * @param string $fieldName
   *   The field machine name.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return mixed
   *   The extracted field value.
   */
  private function extractFieldValue(ContentEntityInterface $entity, string $fieldName): mixed {
    $fieldList = $entity->get($fieldName);
    $values = $fieldList->getValue();

    if (empty($values)) {
      return NULL;
    }

    $targetType = $this->resolveEntityReferenceTargetType($fieldList->getFieldDefinition());

    if ($targetType !== NULL) {
      $ids = array_map(fn (array $item) => (int) $item['target_id'], $values);
      $isSingle = count($ids) === 1;
      $expanded = $this->expandReferenceIds($ids, $isSingle, $targetType);
      if ($expanded !== NULL) {
        return $expanded;
      }
    }

    if (count($values) === 1) {
      return $this->unwrapItem(reset($values));
    }

    return array_map(fn (mixed $item) => $this->unwrapItem($item), $values);
  }

  /**
   * Returns the target entity type for an entity reference field definition.
   *
   * Returns NULL when the field definition is unavailable or the field is not
   * an entity reference type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface|null $fieldDefinition
   *   The field definition, or NULL when unavailable.
   *
   * @return string|null
   *   The target entity type ID, or NULL when not an entity reference field.
   */
  private function resolveEntityReferenceTargetType(?FieldDefinitionInterface $fieldDefinition): ?string {
    if ($fieldDefinition === NULL) {
      return NULL;
    }

    $entityReferenceTypes = ['entity_reference', 'entity_reference_revisions'];

    if (! in_array($fieldDefinition->getType(), $entityReferenceTypes, strict: TRUE)) {
      return NULL;
    }

    $targetType = $fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');

    return is_string($targetType) && $targetType !== '' ? $targetType : NULL;
  }

  /**
   * Expands entity reference IDs for a nested reference field.
   *
   * Returns NULL when no matching view display exists for the target entity
   * type, allowing the caller to fall back to raw value extraction.
   *
   * @param int[] $ids
   *   The target entity IDs.
   * @param bool $isSingleValue
   *   Whether the original field held a single reference.
   * @param string $targetType
   *   The target entity type ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return mixed
   *   The expanded data, or NULL when no matching display exists.
   */
  private function expandReferenceIds(array $ids, bool $isSingleValue, string $targetType): mixed {
    $storage = $this->entityTypeManager->getStorage($targetType);
    $entities = $storage->loadMultiple($ids);

    $results = [];

    foreach ($ids as $id) {
      $entity = $entities[$id] ?? NULL;

      if (! $entity instanceof ContentEntityInterface) {
        return NULL;
      }

      $display = $this->loadDisplay($entity);

      if ($display === NULL) {
        return NULL;
      }

      $results[] = $this->expandEntity($entity, $id);
    }

    return $isSingleValue ? reset($results) : $results;
  }

  /**
   * Unwraps a single field item array to its scalar value if it has one key.
   *
   * @param mixed $item
   *   A field item value.
   *
   * @return mixed
   *   The unwrapped value or the original item.
   */
  private function unwrapItem(mixed $item): mixed {
    if (! is_array($item)) {
      return $item;
    }

    return count($item) === 1 ? reset($item) : $item;
  }

  /**
   * Builds the entity type options for the config form select element.
   *
   * @return array<string, string>
   *   Options keyed by entity type ID, valued by label.
   */
  private function buildEntityTypeOptions(): array {
    $options = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $definition) {
      if ($definition->getGroup() === 'content') {
        $options[$entityTypeId] = (string) $definition->getLabel();
      }
    }

    asort($options);

    return $options;
  }

  /**
   * Resolves the effective target_type value for the current form build.
   *
   * During an AJAX rebuild the plugin is re-instantiated from the saved
   * configuration, so $this->configuration['target_type'] still holds the
   * previously-saved value. To render the correct view mode options we must
   * read the user's current selection from the raw form input first.
   *
   * SubformState::getUserInput() delegates to the parent FormStateInterface and
   * returns the complete unscoped input, so the target_type value lives at
   * ['mutation_config']['target_type'] in that array.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The subform state passed to buildConfigurationForm().
   *
   * @return string
   *   The currently selected target entity type ID, or empty string.
   */
  private function resolveCurrentTargetType(FormStateInterface $form_state): string {
    $userInput = $form_state->getUserInput();
    $fromInput = $userInput['mutation_config']['target_type'] ?? NULL;

    if (is_string($fromInput) && $fromInput !== '') {
      return $fromInput;
    }

    return $this->configuration['target_type'];
  }

  /**
   * Builds the view mode options for the config form select element.
   *
   * Falls back to all view modes when no target type is given.
   *
   * @param string $targetType
   *   The entity type ID to scope the view mode options, or empty string.
   *
   * @return array<string, string>
   *   Options keyed by view mode ID, valued by label.
   */
  private function buildViewModeOptions(string $targetType): array {
    if ($targetType !== '') {
      return $this->entityDisplayRepository->getViewModeOptions($targetType);
    }

    $options = [];

    foreach ($this->entityDisplayRepository->getAllViewModes() as $viewModes) {
      foreach ($viewModes as $viewModeId => $viewModeInfo) {
        $options[$viewModeId] ??= (string) $viewModeInfo['label'];
      }
    }

    return $options;
  }
}
