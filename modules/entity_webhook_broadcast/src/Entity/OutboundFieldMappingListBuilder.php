<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of OutboundFieldMapping config entities.
 *
 * Filters the list to only show field mappings belonging to the subscription
 * identified by the current route's outbound_subscription parameter.
 */
class OutboundFieldMappingListBuilder extends ConfigEntityListBuilder {

  /**
   * The current route match service.
   *
   * Not readonly because of DependencySerializationTrait requirements.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs an OutboundFieldMappingListBuilder.
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
    $subscription = $this->resolveSubscription();

    if ($subscription === NULL) {
      return [];
    }

    $ids = $this->storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('subscription_id', $subscription->id())
      ->sort('output_key')
      ->execute();

    /** @var array<string, \Drupal\entity_webhook_broadcast\Entity\OutboundFieldMappingInterface> $entities */
    $entities = $this->storage->loadMultiple($ids);

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['entity_field'] = $this->t('Entity Field');
    $header['output_key'] = $this->t('Output Key');
    $header['mutation'] = $this->t('Mutation');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof OutboundFieldMappingInterface);

    $row['label'] = $entity->label();
    $row['entity_field'] = $entity->getEntityField();
    $row['output_key'] = $entity->getOutputKey();
    $row['mutation'] = $entity->getMutationPlugin() !== '' ? $entity->getMutationPlugin() : $this->t('None');

    return $row + parent::buildRow($entity);
  }

  /**
   * Resolves the current OutboundSubscription from the route parameter.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null
   *   The subscription, or NULL if not present in the route.
   */
  protected function resolveSubscription(): ?OutboundSubscriptionInterface {
    $subscription = $this->routeMatch->getParameter('outbound_subscription');

    return $subscription instanceof OutboundSubscriptionInterface ? $subscription : NULL;
  }

}
