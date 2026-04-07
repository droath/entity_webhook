<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\entity_webhook\Form\WebhookEndpointForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Defines the WebhookEndpoint config entity.
 *
 * Represents a single webhook URL endpoint, defining the target Drupal entity
 * type and the source type IDs that may post payloads to this endpoint.
 */
#[ConfigEntityType(
  id: 'webhook_endpoint',
  label: new TranslatableMarkup('Webhook Endpoint'),
  label_collection: new TranslatableMarkup('Webhook Endpoints'),
  label_singular: new TranslatableMarkup('webhook endpoint'),
  label_plural: new TranslatableMarkup('webhook endpoints'),
  config_prefix: 'webhook_endpoint',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => WebhookEndpointListBuilder::class,
    'form' => [
      'add' => WebhookEndpointForm::class,
      'edit' => WebhookEndpointForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'add-form' => '/admin/config/services/entity-webhook/endpoints/add',
    'edit-form' => '/admin/config/services/entity-webhook/endpoints/{webhook_endpoint}',
    'delete-form' => '/admin/config/services/entity-webhook/endpoints/{webhook_endpoint}/delete',
    'collection' => '/admin/config/services/entity-webhook/endpoints',
    'source_types' => '/admin/config/services/entity-webhook/endpoints/{webhook_endpoint}/source-types',
  ],
  admin_permission: 'administer entity_webhook',
  label_count: [
    'singular' => '@count webhook endpoint',
    'plural' => '@count webhook endpoints',
  ],
  config_export: [
    'id',
    'label',
    'target_entity_type',
    'target_entity_bundle',
    'source_types',
    'processing_mode',
  ],
)]
class WebhookEndpoint extends ConfigEntityBase implements WebhookEndpointInterface {
  /** The endpoint machine name. */
  protected string $id = '';

  /** The endpoint human-readable label. */
  protected string $label = '';

  /** The target Drupal entity type machine name, or null if not set. */
  protected ?string $target_entity_type = NULL;

  /** The target Drupal entity bundle machine name, or null if no bundle filter. */
  protected ?string $target_entity_bundle = NULL;

  /**
   * The list of associated WebhookSourceType IDs.
   *
   * @var string[]
   */
  protected array $source_types = [];

  /** The processing mode: 'async' (queued) or 'sync' (real-time). */
  protected string $processing_mode = 'async';

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId(): ?string {
    return $this->target_entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityBundle(): ?string {
    return $this->target_entity_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceTypeIds(): array {
    return $this->source_types;
  }

  /**
   * {@inheritdoc}
   */
  public function hasSourceType(string $sourceTypeId): bool {
    return in_array($sourceTypeId, $this->source_types, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function addSourceType(string $sourceTypeId): static {
    if (!in_array($sourceTypeId, $this->source_types, TRUE)) {
      $this->source_types[] = $sourceTypeId;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeSourceType(string $sourceTypeId): static {
    $this->source_types = array_values(array_filter(
      $this->source_types,
      static fn (string $id): bool => $id !== $sourceTypeId,
    ));

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessingMode(): string {
    return $this->processing_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function isSync(): bool {
    return $this->processing_mode === 'sync';
  }
}
