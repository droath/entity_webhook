<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;

/**
 * Provides a listing of WebhookEndpoint config entities.
 */
class WebhookEndpointListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Endpoint');
    $header['id'] = $this->t('Machine name');
    $header['target_entity_type'] = $this->t('Target Entity Type');
    $header['source_types'] = $this->t('Source Types');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    $operations['manage_source_types'] = [
      'title' => $this->t('Manage source types'),
      'url' => Url::fromRoute('entity.webhook_endpoint.source_types', [
        'webhook_endpoint' => $entity->id(),
      ]),
      'weight' => 10,
    ];

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof WebhookEndpointInterface);
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['target_entity_type'] = $entity->getTargetEntityTypeId();
    $row['source_types'] = implode(', ', $entity->getSourceTypeIds());

    return $row + parent::buildRow($entity);
  }
}
