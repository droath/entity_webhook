<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook_broadcast\Attribute\OutboundValueResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reads a single Drupal entity field and returns its raw value.
 */
#[OutboundValueResolver(
  id: 'entity_field',
  label: new TranslatableMarkup('Entity Field'),
  description: new TranslatableMarkup('Reads a field value directly from the Drupal entity.'),
)]
class EntityFieldResolver extends OutboundValueResolverBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an EntityFieldResolver plugin.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
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
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'entity_field' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(EntityInterface $entity): mixed {
    $fieldName = $this->configuration['entity_field'];

    if ($fieldName === '' || !$entity->hasField($fieldName)) {
      return NULL;
    }

    $fieldList = $entity->get($fieldName);
    $values = $fieldList->getValue();

    if (empty($values)) {
      return NULL;
    }

    if (count($values) === 1) {
      $first = reset($values);

      return is_array($first) && count($first) === 1 ? reset($first) : $first;
    }

    return array_map(
      fn($item) => is_array($item) && count($item) === 1 ? reset($item) : $item,
      $values,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['entity_field'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Entity Field'),
      '#options' => $this->buildFieldOptions($form_state),
      '#default_value' => $this->configuration['entity_field'],
      '#empty_option' => new TranslatableMarkup('- Select -'),
      '#empty_value' => '',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['entity_field'] = $form_state->getValue('entity_field');
  }

  /**
   * Builds entity field options for the configuration form select element.
   *
   * Reads entity type and bundle from a form state storage key set by
   * OutboundFieldMappingForm before building the resolver subform.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state (may be a SubformState).
   *
   * @return array<string, string>
   *   Options keyed by field name, valued by field label.
   */
  private function buildFieldOptions(FormStateInterface $form_state): array {
    $context = $form_state->get('outbound_resolver_context');

    if (!is_array($context)) {
      return [];
    }

    $entityTypeId = $context['entity_type_id'] ?? '';
    $bundle = $context['bundle'] ?? '';

    if ($entityTypeId === '') {
      return [];
    }

    $effectiveBundle = $bundle !== '' ? $bundle : $entityTypeId;
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $effectiveBundle);

    $options = [];

    foreach ($fieldDefinitions as $fieldName => $definition) {
      $options[$fieldName] = (string) $definition->getLabel();
    }

    asort($options);

    return $options;
  }

}
