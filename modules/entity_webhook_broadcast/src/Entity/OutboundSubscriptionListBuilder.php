<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of OutboundSubscription config entities.
 *
 * Filters the list to only show subscriptions belonging to the endpoint
 * identified by the current route's outbound_endpoint parameter.
 */
class OutboundSubscriptionListBuilder extends ConfigEntityListBuilder {

  /**
   * The current route match service.
   *
   * Not readonly because of DependencySerializationTrait requirements.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs an OutboundSubscriptionListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($entity_type, $storage);
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load(): array {
    $endpoint = $this->resolveEndpoint();

    if ($endpoint === NULL) {
      return [];
    }

    $ids = $this->storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('endpoint_id', $endpoint->id())
      ->sort('label')
      ->execute();

    /** @var array<string, \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface> $entities */
    $entities = $this->storage->loadMultiple($ids);

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['url'] = $this->t('URL');
    $header['signing_algorithm'] = $this->t('Signing Algorithm');
    $header['active'] = $this->t('Active');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof OutboundSubscriptionInterface);

    $row['label'] = $entity->label();
    $row['url'] = $entity->getUrl();
    $row['signing_algorithm'] = $entity->getSigningAlgorithm();
    $row['active'] = $entity->isActive() ? $this->t('Yes') : $this->t('No');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    assert($entity instanceof OutboundSubscriptionInterface);

    $endpoint = $this->resolveEndpoint();
    if ($endpoint === NULL) {
      return $operations;
    }

    $operations['field_mappings'] = [
      'title' => $this->t('Field Mappings'),
      'url' => Url::fromRoute('entity.outbound_field_mapping.collection', [
        'outbound_endpoint' => $endpoint->id(),
        'outbound_subscription' => $entity->id(),
      ]),
      'weight' => 5,
    ];

    $operations['test_webhook'] = [
      'title' => $this->t('Test Webhook'),
      'url' => Url::fromRoute('entity_webhook_broadcast.test_webhook', [
        'outbound_endpoint' => $endpoint->id(),
        'outbound_subscription' => $entity->id(),
      ]),
      'weight' => 10,
    ];

    $operations['delivery_logs'] = [
      'title' => $this->t('Delivery Logs'),
      'url' => Url::fromRoute('entity_webhook_broadcast.subscription_delivery_logs', [
        'outbound_endpoint' => $endpoint->id(),
        'outbound_subscription' => $entity->id(),
      ]),
      'weight' => 15,
    ];

    return $operations;
  }

  /**
   * Resolves the current OutboundEndpoint from the route parameter.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null
   *   The endpoint, or NULL if not present in the route.
   */
  protected function resolveEndpoint(): ?OutboundEndpointInterface {
    $endpoint = $this->routeMatch->getParameter('outbound_endpoint');

    return $endpoint instanceof OutboundEndpointInterface ? $endpoint : NULL;
  }

}
