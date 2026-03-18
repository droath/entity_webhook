<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\entity_webhook\Form\WebhookFieldMappingForm;

/**
 * Defines the WebhookFieldMapping config entity.
 *
 * Each instance maps a single Drupal entity field to a value extracted from a
 * webhook payload, using a ValueResolver plugin and an optional
 * FieldValueMutation plugin. Instances belong to a parent WebhookSourceType
 * and are cascade-deleted when that source type is deleted.
 *
 * ID format: {source_type_id}.{entity_field_name}
 */
#[ConfigEntityType(
  id: 'webhook_field_mapping',
  label: new TranslatableMarkup('Webhook Field Mapping'),
  label_collection: new TranslatableMarkup('Webhook Field Mappings'),
  label_singular: new TranslatableMarkup('webhook field mapping'),
  label_plural: new TranslatableMarkup('webhook field mappings'),
  config_prefix: 'webhook_field_mapping',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => WebhookFieldMappingListBuilder::class,
    'form' => [
      'add' => WebhookFieldMappingForm::class,
      'edit' => WebhookFieldMappingForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'collection' => '/admin/config/services/entity-webhook/endpoints/{webhook_endpoint}/source-types/{webhook_source_type}/field-mappings',
    'add-form' => '/admin/config/services/entity-webhook/endpoints/{webhook_endpoint}/source-types/{webhook_source_type}/field-mappings/add',
    'edit-form' => '/admin/config/services/entity-webhook/endpoints/{webhook_endpoint}/source-types/{webhook_source_type}/field-mappings/{webhook_field_mapping}/edit',
    'delete-form' => '/admin/config/services/entity-webhook/endpoints/{webhook_endpoint}/source-types/{webhook_source_type}/field-mappings/{webhook_field_mapping}/delete',
  ],
  admin_permission: 'administer entity_webhook',
  label_count: [
    'singular' => '@count webhook field mapping',
    'plural' => '@count webhook field mappings',
  ],
  config_export: [
    'id',
    'label',
    'source_type',
    'entity_field',
    'is_identifier',
    'resolver',
    'resolver_config',
    'mutation_plugin',
    'mutation_config',
  ],
)]
class WebhookFieldMapping extends ConfigEntityBase implements WebhookFieldMappingInterface {
  /** The field mapping machine name ({source_type}.{entity_field}). */
  protected string $id = '';

  /** The human-readable label. */
  protected string $label = '';

  /** The parent WebhookSourceType machine name. */
  protected string $source_type = '';

  /** The target Drupal entity field machine name. */
  protected string $entity_field = '';

  /** Whether this field is used as an identifier for entity upsert lookup. */
  protected bool $is_identifier = FALSE;

  /** The ValueResolver plugin ID. */
  protected string $resolver = 'json_path';

  /**
   * The ValueResolver plugin configuration.
   *
   * @var array<string, mixed>
   */
  protected array $resolver_config = [];

  /** The FieldValueMutation plugin ID, or empty string when none is applied. */
  protected string $mutation_plugin = '';

  /**
   * The FieldValueMutation plugin configuration.
   *
   * @var array<string, mixed>
   */
  protected array $mutation_config = [];

  /**
   * {@inheritdoc}
   */
  public function getSourceTypeId(): string {
    return $this->source_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceType(): ?WebhookSourceTypeInterface {
    if ($this->source_type === '') {
      return NULL;
    }

    $entity = \Drupal::entityTypeManager()
      ->getStorage('webhook_source_type')
      ->load($this->source_type);

    return $entity instanceof WebhookSourceTypeInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityField(): string {
    return $this->entity_field;
  }

  /**
   * {@inheritdoc}
   */
  public function isIdentifier(): bool {
    return $this->is_identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolver(): string {
    return $this->resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolverConfig(): array {
    return $this->resolver_config;
  }

  /**
   * {@inheritdoc}
   */
  public function getMutationPlugin(): string {
    return $this->mutation_plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getMutationConfig(): array {
    return $this->mutation_config;
  }

  /**
   * {@inheritdoc}
   */
  public function toFieldMapping(): FieldMapping {
    return new FieldMapping(
      entityField: $this->entity_field,
      isIdentifier: $this->is_identifier,
      mutationPlugin: $this->mutation_plugin,
      mutationConfig: $this->mutation_config,
      resolver: $this->resolver,
      resolverConfig: $this->resolver_config,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static {
    parent::calculateDependencies();

    $sourceType = $this->getSourceType();
    if ($sourceType !== NULL) {
      $this->addDependency('config', $sourceType->getConfigDependencyName());
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function urlRouteParameters($rel): array {
    $parameters = parent::urlRouteParameters($rel);

    $sourceType = $this->getSourceType();
    if ($sourceType !== NULL) {
      $parameters['webhook_source_type'] = $sourceType->id();
      $parameters['webhook_endpoint'] = $sourceType->getEndpointId();
    }

    return $parameters;
  }
}
