<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_webhook_broadcast\Attribute\OutboundValueResolver;
use Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverBase;

/**
 * Resolves a field on a referenced entity via an entity reference field.
 */
#[OutboundValueResolver(
  id: 'entity_reference_field',
  label: new TranslatableMarkup('Entity Reference Field'),
  description: new TranslatableMarkup('Resolves through an entity reference to read a field on the referenced entity.'),
)]
class EntityReferenceFieldResolver extends OutboundValueResolverBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an EntityReferenceFieldResolver plugin.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
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
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function defaultConfiguration(): array {
    return [
      'entity_field' => '',
      'target_field' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(EntityInterface $entity): mixed {
    $entityField = $this->configuration['entity_field'];

    if ($entityField === '' || !$entity->hasField($entityField)) {
      return NULL;
    }

    $referencedEntity = $this->loadFirstReferencedEntity($entity, $entityField);

    if ($referencedEntity === NULL) {
      return NULL;
    }

    $targetField = $this->configuration['target_field'];

    if ($targetField === '' || !$referencedEntity->hasField($targetField)) {
      return NULL;
    }

    $values = $referencedEntity->get($targetField)->getValue();

    if (empty($values)) {
      return NULL;
    }

    $first = reset($values);

    return is_array($first) && count($first) === 1 ? reset($first) : $first;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['entity_field'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Entity Reference Field'),
      '#description' => new TranslatableMarkup('The entity reference field on the source entity.'),
      '#options' => $this->buildFieldOptions($form_state),
      '#default_value' => $this->configuration['entity_field'],
      '#empty_option' => new TranslatableMarkup('- Select -'),
      '#empty_value' => '',
      '#required' => TRUE,
    ];

    $form['target_field'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Target Field'),
      '#description' => new TranslatableMarkup('The field machine name to read from the referenced entity.'),
      '#default_value' => $this->configuration['target_field'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['entity_field'] = $form_state->getValue('entity_field');
    $this->configuration['target_field'] = $form_state->getValue('target_field');
  }

  /**
   * Loads the first referenced entity from an entity reference field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The source entity.
   * @param string $fieldName
   *   The entity reference field name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The first referenced entity, or NULL when the reference is empty or the
   *   referenced entity cannot be loaded.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function loadFirstReferencedEntity(EntityInterface $entity, string $fieldName): ?EntityInterface {
    $values = $entity->get($fieldName)->getValue();

    if (empty($values)) {
      return NULL;
    }

    $first = reset($values);
    $targetId = $first['target_id'] ?? NULL;

    if ($targetId === NULL) {
      return NULL;
    }

    $fieldDefinition = $entity->getFieldDefinition($fieldName);
    $targetType = $fieldDefinition?->getFieldStorageDefinition()
      ->getSetting('target_type');

    if (!is_string($targetType) || $targetType === '') {
      return NULL;
    }

    $loaded = $this->entityTypeManager->getStorage($targetType)
      ->load($targetId);

    return $loaded instanceof EntityInterface ? $loaded : NULL;
  }

  /**
   * Builds entity field options for the configuration form a select element.
   *
   * Reads entity type and bundle from the outbound_resolver_context storage
   * key set by OutboundFieldMappingForm before building the resolver subform.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state (it may be a SubformState).
   *
   * @return array<string, string>
   *   Options keyed by field name, valued by field label.
   */
  private function buildFieldOptions(FormStateInterface $form_state): array {
    $context = $form_state->get('outbound_resolver_context');

    if (!is_array($context)) {
      return [];
    }
    $bundle = $context['bundle'] ?? '';
    $entityTypeId = $context['entity_type_id'] ?? '';

    if ($entityTypeId === '') {
      return [];
    }

    $effectiveBundle = $bundle !== '' ? $bundle : $entityTypeId;
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $effectiveBundle);

    $referenceTypes = ['entity_reference', 'entity_revision_reference'];
    $fieldDefinitions = array_filter($fieldDefinitions, static function ($definition) use ($referenceTypes): bool {
      return in_array($definition->getType(), $referenceTypes, TRUE);
    });

    $options = array_map(static function ($definition) {
      return (string) $definition->getLabel();
    }, $fieldDefinitions);

    asort($options);

    return $options;
  }

}
