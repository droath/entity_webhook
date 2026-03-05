<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of EntityWebhookPolling config entities.
 */
class EntityWebhookPollingListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['cron_expression'] = $this->t('Schedule');
    $header['polling_provider'] = $this->t('Provider');
    $header['endpoint_id'] = $this->t('Endpoint');
    $header['source_type_id'] = $this->t('Source Type');
    $header['status'] = $this->t('Status');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof EntityWebhookPollingInterface);
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['cron_expression'] = $entity->getCronExpression();
    $row['polling_provider'] = $entity->getPollingProvider();
    $row['endpoint_id'] = $entity->getEndpointId();
    $row['source_type_id'] = $entity->getSourceTypeId();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');

    return $row + parent::buildRow($entity);
  }

}
