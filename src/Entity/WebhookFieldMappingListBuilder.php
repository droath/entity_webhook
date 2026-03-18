<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of WebhookFieldMapping config entities.
 *
 * Filters the list to only show mappings belonging to the source type
 * identified by the current route's webhook_source_type parameter.
 */
class WebhookFieldMappingListBuilder extends ConfigEntityListBuilder {
  /**
   * The current route match service.
   *
   * Not readonly because of DependencySerializationTrait requirements.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs a WebhookFieldMappingListBuilder.
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
    $sourceType = $this->resolveSourceType();

    if ($sourceType === NULL) {
      return [];
    }

    $ids = $this->storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('source_type', $sourceType->id())
      ->sort('entity_field')
      ->execute();

    /** @var array<string, \Drupal\entity_webhook\Entity\WebhookFieldMappingInterface> $entities */
    $entities = $this->storage->loadMultiple($ids);

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['entity_field'] = $this->t('Entity Field');
    $header['resolver'] = $this->t('Resolver');
    $header['mutation'] = $this->t('Mutation');
    $header['is_identifier'] = $this->t('Identifier');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof WebhookFieldMappingInterface);

    $row['label'] = $entity->label();
    $row['entity_field'] = $entity->getEntityField();
    $row['resolver'] = $entity->getResolver();
    $row['mutation'] = $entity->getMutationPlugin() !== '' ? $entity->getMutationPlugin() : $this->t('None');
    $row['is_identifier'] = $entity->isIdentifier() ? $this->t('Yes') : $this->t('No');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to build operations.
   *
   * @return array<string, mixed>
   *   An associative array of operations.
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    assert($entity instanceof WebhookFieldMappingInterface);

    $endpoint = $this->resolveEndpoint();
    $sourceType = $this->resolveSourceType();

    if ($endpoint === NULL || $sourceType === NULL) {
      return parent::getDefaultOperations($entity);
    }

    $routeParams = [
      'webhook_endpoint' => $endpoint->id(),
      'webhook_source_type' => $sourceType->id(),
      'webhook_field_mapping' => $entity->id(),
    ];

    $operations = parent::getDefaultOperations($entity);

    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('entity.webhook_field_mapping.edit_form', $routeParams),
        'weight' => 10,
      ];
    }

    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('entity.webhook_field_mapping.delete_form', $routeParams),
        'weight' => 100,
      ];
    }

    // Inject parent route parameters into any contrib-added operations
    // (e.g., Devel's devel_load) that use routes under the same path hierarchy.
    foreach ($operations as &$operation) {
      if (isset($operation['url']) && $operation['url'] instanceof Url) {
        $existingParams = $operation['url']->getRouteParameters();
        $operation['url']->setRouteParameters($routeParams + $existingParams);
      }
    }

    return $operations;
  }

  /**
   * Resolves the current WebhookSourceType from the route parameter.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface|null
   *   The source type, or NULL if not present in the route.
   */
  protected function resolveSourceType(): ?WebhookSourceTypeInterface {
    $sourceType = $this->routeMatch->getParameter('webhook_source_type');

    return $sourceType instanceof WebhookSourceTypeInterface ? $sourceType : NULL;
  }

  /**
   * Resolves the current WebhookEndpoint from the route parameter.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null
   *   The endpoint, or NULL if not present in the route.
   */
  protected function resolveEndpoint(): ?WebhookEndpointInterface {
    $endpoint = $this->routeMatch->getParameter('webhook_endpoint');

    return $endpoint instanceof WebhookEndpointInterface ? $endpoint : NULL;
  }
}
