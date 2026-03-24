<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\entity_webhook_broadcast\Form\OutboundFieldMappingForm;

/**
 * Defines the OutboundFieldMapping config entity.
 *
 * Each instance maps a single Drupal entity field to a JSON output key in the
 * outbound webhook payload, with an optional FieldValueMutation plugin applied.
 * Instances belong to a parent OutboundSubscription and are cascade-deleted
 * when that subscription is deleted.
 *
 * ID format: {subscription_id}.{output_key}
 */
#[ConfigEntityType(
  id: 'outbound_field_mapping',
  label: new TranslatableMarkup('Outbound Field Mapping'),
  label_collection: new TranslatableMarkup('Outbound Field Mappings'),
  label_singular: new TranslatableMarkup('outbound field mapping'),
  label_plural: new TranslatableMarkup('outbound field mappings'),
  config_prefix: 'outbound_field_mapping',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => OutboundFieldMappingListBuilder::class,
    'form' => [
      'add' => OutboundFieldMappingForm::class,
      'edit' => OutboundFieldMappingForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'collection' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/subscriptions/{outbound_subscription}/field-mappings',
    'add-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/subscriptions/{outbound_subscription}/field-mappings/add',
    'edit-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/subscriptions/{outbound_subscription}/field-mappings/{outbound_field_mapping}/edit',
    'delete-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/subscriptions/{outbound_subscription}/field-mappings/{outbound_field_mapping}/delete',
  ],
  admin_permission: 'administer entity_webhook_broadcast',
  label_count: [
    'singular' => '@count outbound field mapping',
    'plural' => '@count outbound field mappings',
  ],
  config_export: [
    'id',
    'label',
    'subscription_id',
    'resolver',
    'resolver_config',
    'output_key',
    'mutation_plugin',
    'mutation_config',
  ],
)]
class OutboundFieldMapping extends ConfigEntityBase implements OutboundFieldMappingInterface
{

  /** The field mapping machine name ({subscription_id}.{output_key}). */
  protected string $id = '';

  /** The human-readable label. */
  protected string $label = '';

  /** The parent OutboundSubscription machine name. */
  protected string $subscription_id = '';

  /** The outbound value resolver plugin ID. */
  protected string $resolver = '';

  /**
   * The outbound value resolver plugin configuration.
   *
   * @var array<string, mixed>
   */
  protected array $resolver_config = [];

  /** The output JSON key name. */
  protected string $output_key = '';

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
  public function getSubscriptionId(): string
  {
    return $this->subscription_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscription(): ?OutboundSubscriptionInterface
  {
    if ($this->subscription_id === '') {
      return NULL;
    }

    $entity = \Drupal::entityTypeManager()
      ->getStorage('outbound_subscription')
      ->load($this->subscription_id);

    return $entity instanceof OutboundSubscriptionInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolver(): string
  {
    return $this->resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolverConfig(): array
  {
    return $this->resolver_config;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputKey(): string
  {
    return $this->output_key;
  }

  /**
   * {@inheritdoc}
   */
  public function getMutationPlugin(): string
  {
    return $this->mutation_plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getMutationConfig(): array
  {
    return $this->mutation_config;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static
  {
    parent::calculateDependencies();

    $subscription = $this->getSubscription();
    if ($subscription !== NULL) {
      $this->addDependency('config', $subscription->getConfigDependencyName());
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function urlRouteParameters($rel): array
  {
    $parameters = parent::urlRouteParameters($rel);

    $subscription = $this->getSubscription();
    if ($subscription !== NULL) {
      $parameters['outbound_subscription'] = $subscription->id();
      $parameters['outbound_endpoint'] = $subscription->getEndpointId();
    }

    return $parameters;
  }

}
