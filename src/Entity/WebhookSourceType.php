<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Form\WebhookSourceTypeForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Defines the WebhookSourceType config entity.
 *
 * Stores identifier configuration and verification plugin settings for a
 * specific external payload source. Field mappings are stored as child
 * WebhookFieldMapping config entities and loaded dynamically.
 */
#[ConfigEntityType(
  id: 'webhook_source_type',
  label: new TranslatableMarkup('Webhook Source Type'),
  label_collection: new TranslatableMarkup('Webhook Source Types'),
  label_singular: new TranslatableMarkup('webhook source type'),
  label_plural: new TranslatableMarkup('webhook source types'),
  config_prefix: 'webhook_source_type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'form' => [
      'add' => WebhookSourceTypeForm::class,
      'edit' => WebhookSourceTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'edit-form' => '/admin/config/services/entity-webhook/endpoints/{webhook_endpoint}/source-types/{webhook_source_type}/edit',
  ],
  admin_permission: 'administer entity_webhook',
  label_count: [
    'singular' => '@count webhook source type',
    'plural' => '@count webhook source types',
  ],
  config_export: [
    'id',
    'label',
    'endpoint',
    'operation',
    'verification_plugin',
    'verification_config',
    'payload_processor',
    'payload_processor_config',
  ],
)]
class WebhookSourceType extends ConfigEntityBase implements WebhookSourceTypeInterface {
  /** The source type machine name. */
  protected string $id = '';

  /** The source type human-readable label. */
  protected string $label = '';

  /** The parent endpoint machine name. */
  protected string $endpoint = '';

  /** The entity operation: 'upsert' or 'delete'. */
  protected string $operation = 'upsert';

  /** The verification plugin ID. */
  protected string $verification_plugin = '';

  /**
   * The verification plugin configuration.
   *
   * @var array<string, mixed>
   */
  protected array $verification_config = [];

  /** The payload processor plugin ID. */
  protected string $payload_processor = '';

  /**
   * The payload processor plugin configuration.
   *
   * @var array<string, mixed>
   */
  protected array $payload_processor_config = [];

  /**
   * {@inheritdoc}
   */
  public function getOperation(): string {
    return $this->operation;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointId(): string {
    return $this->endpoint;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(): ?WebhookEndpointInterface {
    if ($this->endpoint === '') {
      return NULL;
    }

    $entity = \Drupal::entityTypeManager()
      ->getStorage('webhook_endpoint')
      ->load($this->endpoint);

    return $entity instanceof WebhookEndpointInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMappings(): array {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('webhook_field_mapping')
      ->loadByProperties(['source_type' => $this->id()]);

    $result = [];
    foreach ($entities as $entity) {
      if ($entity instanceof WebhookFieldMappingInterface) {
        $result[] = $entity->toFieldMapping();
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifierMappings(): array {
    return array_values(array_filter(
      $this->getFieldMappings(),
      static fn (FieldMapping $mapping) => $mapping->isIdentifier,
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getVerificationPlugin(): string {
    return $this->verification_plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getVerificationConfig(): array {
    return $this->verification_config;
  }

  /**
   * {@inheritdoc}
   */
  public function getPayloadProcessor(): string {
    return $this->payload_processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPayloadProcessorConfig(): array {
    return $this->payload_processor_config;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function urlRouteParameters($rel): array {
    $parameters = parent::urlRouteParameters($rel);

    if ($this->endpoint !== '') {
      $parameters['webhook_endpoint'] = $this->endpoint;
    }

    return $parameters;
  }
}
