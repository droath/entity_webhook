<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\entity_webhook_broadcast\Form\OutboundSubscriptionForm;

/**
 * Defines the OutboundSubscription config entity.
 *
 * An OutboundSubscription is a child of an OutboundEndpoint. It defines the
 * destination URL, HMAC signing configuration, and retry policy for webhook
 * delivery. Child OutboundFieldMapping entities define the payload structure.
 */
#[ConfigEntityType(
  id: 'outbound_subscription',
  label: new TranslatableMarkup('Outbound Subscription'),
  label_collection: new TranslatableMarkup('Outbound Subscriptions'),
  label_singular: new TranslatableMarkup('outbound subscription'),
  label_plural: new TranslatableMarkup('outbound subscriptions'),
  config_prefix: 'outbound_subscription',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'active',
  ],
  handlers: [
    'list_builder' => OutboundSubscriptionListBuilder::class,
    'form' => [
      'add' => OutboundSubscriptionForm::class,
      'edit' => OutboundSubscriptionForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'collection' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/subscriptions',
    'add-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/subscriptions/add',
    'edit-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/subscriptions/{outbound_subscription}/edit',
    'delete-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/subscriptions/{outbound_subscription}/delete',
  ],
  admin_permission: 'administer entity_webhook_broadcast',
  label_count: [
    'singular' => '@count outbound subscription',
    'plural' => '@count outbound subscriptions',
  ],
  config_export: [
    'id',
    'label',
    'endpoint_id',
    'url',
    'secret',
    'signing_algorithm',
    'retry_max_attempts',
    'retry_base_delay',
    'active',
  ],
)]
class OutboundSubscription extends ConfigEntityBase implements OutboundSubscriptionInterface
{

  /** The subscription machine name. */
  protected string $id = '';

  /** The human-readable label. */
  protected string $label = '';

  /** The parent OutboundEndpoint machine name. */
  protected string $endpoint_id = '';

  /** The destination webhook URL. */
  protected string $url = '';

  /** The shared HMAC secret. NULL when signing is disabled. */
  protected ?string $secret = NULL;

  /** The HMAC signing algorithm. */
  protected string $signing_algorithm = 'sha256';

  /** The maximum number of delivery attempts. */
  protected int $retry_max_attempts = 5;

  /** The base retry delay in seconds for exponential backoff. */
  protected int $retry_base_delay = 60;

  /** Whether this subscription is active. */
  protected bool $active = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getEndpointId(): string
  {
    return $this->endpoint_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(): ?OutboundEndpointInterface
  {
    if ($this->endpoint_id === '') {
      return NULL;
    }

    $entity = \Drupal::entityTypeManager()
      ->getStorage('outbound_endpoint')
      ->load($this->endpoint_id);

    return $entity instanceof OutboundEndpointInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): string
  {
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function getSecret(): ?string
  {
    return $this->secret !== '' ? $this->secret : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSigningAlgorithm(): string
  {
    return $this->signing_algorithm;
  }

  /**
   * {@inheritdoc}
   */
  public function getRetryMaxAttempts(): int
  {
    return $this->retry_max_attempts;
  }

  /**
   * {@inheritdoc}
   */
  public function getRetryBaseDelay(): int
  {
    return $this->retry_base_delay;
  }

  /**
   * {@inheritdoc}
   */
  public function status(): bool
  {
    return $this->active;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status): static
  {
    $this->active = (bool)$status;
    return $this;
  }

  public function isActive(): bool
  {
    return $this->active;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static
  {
    parent::calculateDependencies();

    $endpoint = $this->getEndpoint();
    if ($endpoint !== NULL) {
      $this->addDependency('config', $endpoint->getConfigDependencyName());
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

    if ($this->endpoint_id !== '') {
      $parameters['outbound_endpoint'] = $this->endpoint_id;
    }

    return $parameters;
  }

}
