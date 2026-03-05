<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;

/**
 * Provides a listing of WebhookSourceType config entities.
 */
class WebhookSourceTypeListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Source Type');
    $header['id'] = $this->t('Machine name');
    $header['field_mappings'] = $this->t('Field Mappings');
    $header['verification'] = $this->t('Verification');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof WebhookSourceTypeInterface);
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['field_mappings'] = count($entity->getFieldMappings());
    $row['verification'] = $entity->getVerificationPlugin() ?: $this->t('None');

    return $row + parent::buildRow($entity);
  }
}
