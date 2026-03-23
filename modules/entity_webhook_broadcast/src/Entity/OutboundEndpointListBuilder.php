<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of OutboundEndpoint config entities.
 */
class OutboundEndpointListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    $operations['manage_subscriptions'] = [
      'title' => $this->t('Manage subscriptions'),
      'url' => Url::fromRoute('entity.outbound_subscription.collection', [
        'outbound_endpoint' => $entity->id(),
      ]),
      'weight' => 5,
    ];

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['entity_type'] = $this->t('Entity Type');
    $header['entity_bundle'] = $this->t('Bundle');
    $header['events'] = $this->t('Events');
    $header['status'] = $this->t('Status');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof OutboundEndpointInterface);

    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['entity_type'] = $entity->getWatchedEntityType();
    $row['entity_bundle'] = $entity->getEntityBundle() ?? $this->t('All bundles');
    $row['events'] = implode(', ', $entity->getEvents());
    $row['status'] = $entity->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');

    return $row + parent::buildRow($entity);
  }

}
