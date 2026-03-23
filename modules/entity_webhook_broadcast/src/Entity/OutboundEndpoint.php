<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\entity_webhook_broadcast\Form\OutboundEndpointForm;

/**
 * Defines the OutboundEndpoint config entity.
 *
 * An OutboundEndpoint watches a specific Drupal entity type, optional bundle,
 * and set of CRUD events. It acts as the top-level parent in the three-tier
 * hierarchy: OutboundEndpoint → OutboundSubscription → OutboundFieldMapping.
 */
#[ConfigEntityType(
  id: 'outbound_endpoint',
  label: new TranslatableMarkup('Outbound Endpoint'),
  label_collection: new TranslatableMarkup('Outbound Endpoints'),
  label_singular: new TranslatableMarkup('outbound endpoint'),
  label_plural: new TranslatableMarkup('outbound endpoints'),
  config_prefix: 'outbound_endpoint',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'status',
  ],
  handlers: [
    'list_builder' => OutboundEndpointListBuilder::class,
    'form' => [
      'add' => OutboundEndpointForm::class,
      'edit' => OutboundEndpointForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'add-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/add',
    'edit-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/edit',
    'delete-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/delete',
    'collection' => '/admin/config/services/entity-webhook/broadcast/endpoints',
  ],
  admin_permission: 'administer entity_webhook_broadcast',
  label_count: [
    'singular' => '@count outbound endpoint',
    'plural' => '@count outbound endpoints',
  ],
  config_export: [
    'id',
    'label',
    'status',
    'entity_type',
    'entity_bundle',
    'events',
  ],
)]
class OutboundEndpoint extends ConfigEntityBase implements OutboundEndpointInterface
{

  /** The endpoint machine name. */
  protected string $id = '';

  /** The human-readable label. */
  protected string $label = '';

  /** The watched entity type machine name. */
  protected string $entity_type = '';

  /** The watched entity bundle machine name, or empty string for all bundles. */
  protected ?string $entity_bundle = NULL;

  /**
   * The CRUD event names to watch.
   *
   * @var string[]
   */
  protected array $events = [];

  /**
   * {@inheritdoc}
   */
  public function getWatchedEntityType(): string
  {
    return $this->entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle(): ?string
  {
    return $this->entity_bundle !== '' ? $this->entity_bundle : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(): array
  {
    return $this->events;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool
  {
    return (bool)$this->status;
  }

}
