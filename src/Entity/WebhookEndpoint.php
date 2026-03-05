<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\entity_webhook\Form\WebhookEndpointForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
    'source_types',
  ],
)]
class WebhookEndpoint extends ConfigEntityBase implements WebhookEndpointInterface {

  /** The endpoint machine name. */
  protected string $id = '';

  /** The endpoint human-readable label. */
  protected string $label = '';

  /** The target Drupal entity type machine name. */
  protected string $target_entity_type = '';

  /**
   * The list of associated WebhookSourceType IDs.
   *
   * @var string[]
   */
  protected array $source_types = [];

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId(): string {
    return $this->target_entity_type;
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

}
