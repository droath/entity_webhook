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
 * Stores field mappings, identifier configuration, and verification plugin
 * settings for a specific external payload source.
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
    'field_mappings',
    'verification_plugin',
    'verification_config',
  ],
)]
class WebhookSourceType extends ConfigEntityBase implements WebhookSourceTypeInterface {
  /** The source type machine name. */
  protected string $id = '';

  /** The source type human-readable label. */
  protected string $label = '';

  /** The parent endpoint machine name. */
  protected string $endpoint = '';

  /**
   * Raw field mappings configuration array.
   *
   * @var array<int, array<string, mixed>>
   */
  protected array $field_mappings = [];

  /** The verification plugin ID. */
  protected string $verification_plugin = '';

  /**
   * The verification plugin configuration.
   *
   * @var array<string, mixed>
   */
  protected array $verification_config = [];

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
    return array_map(
      static fn (array $data) => FieldMapping::fromArray($data),
      $this->field_mappings,
    );
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
}
