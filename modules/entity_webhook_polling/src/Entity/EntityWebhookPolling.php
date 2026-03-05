<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook_polling\Form\EntityWebhookPollingForm;

/**
 * Defines the EntityWebhookPolling config entity.
 *
 * Stores the polling schedule, provider plugin, and endpoint/source type
 * references for a single polling configuration. Payloads fetched by the
 * provider are routed through the core entity webhook queue pipeline with
 * source='polling'.
 */
#[ConfigEntityType(
  id: 'entity_webhook_polling',
  label: new TranslatableMarkup('Polling Configuration'),
  label_collection: new TranslatableMarkup('Polling Configurations'),
  label_singular: new TranslatableMarkup('polling configuration'),
  label_plural: new TranslatableMarkup('polling configurations'),
  label_count: [
    'singular' => '@count polling configuration',
    'plural' => '@count polling configurations',
  ],
  handlers: [
    'list_builder' => 'Drupal\entity_webhook_polling\Entity\EntityWebhookPollingListBuilder',
    'form' => [
      'add' => EntityWebhookPollingForm::class,
      'edit' => EntityWebhookPollingForm::class,
      'delete' => 'Drupal\Core\Entity\EntityDeleteForm',
    ],
  ],
  admin_permission: 'administer entity_webhook_polling',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'status',
  ],
  links: [
    'add-form' => '/admin/config/services/entity-webhook/polling/add',
    'edit-form' => '/admin/config/services/entity-webhook/polling/{entity_webhook_polling}',
    'delete-form' => '/admin/config/services/entity-webhook/polling/{entity_webhook_polling}/delete',
    'collection' => '/admin/config/services/entity-webhook/polling',
  ],
  config_prefix: 'polling',
  config_export: [
    'id',
    'label',
    'status',
    'cron_expression',
    'polling_provider',
    'polling_provider_config',
    'endpoint_id',
    'source_type_id',
  ],
)]
class EntityWebhookPolling extends ConfigEntityBase implements EntityWebhookPollingInterface {

  /**
   * The polling config machine name.
   */
  protected string $id = '';

  /**
   * The polling config human-readable label.
   */
  protected string $label = '';

  /**
   * The cron expression defining the polling schedule.
   */
  protected string $cron_expression = '';

  /**
   * The polling provider plugin ID.
   */
  protected string $polling_provider = '';

  /**
   * The polling provider plugin configuration.
   *
   * @var array<string, mixed>
   */
  protected array $polling_provider_config = [];

  /**
   * The WebhookEndpoint ID this polling config routes payloads to.
   */
  protected string $endpoint_id = '';

  /**
   * The WebhookSourceType ID used for payload processing.
   */
  protected string $source_type_id = '';

  /**
   * {@inheritdoc}
   */
  public function getCronExpression(): string {
    return $this->cron_expression;
  }

  /**
   * {@inheritdoc}
   */
  public function getPollingProvider(): string {
    return $this->polling_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getPollingProviderConfig(): array {
    return $this->polling_provider_config;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointId(): string {
    return $this->endpoint_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceTypeId(): string {
    return $this->source_type_id;
  }

}
