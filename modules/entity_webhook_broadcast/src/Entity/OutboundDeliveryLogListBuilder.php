<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of OutboundDeliveryLog content entities.
 *
 * Displays key delivery information including subscription, entity details,
 * event type, delivery status, attempt count, HTTP status, and creation time.
 *
 * When a subscription context is set via setSubscription(), only logs for
 * that subscription are shown. Otherwise all logs are displayed.
 */
class OutboundDeliveryLogListBuilder extends EntityListBuilder {

  /**
   * The subscription to filter logs by, or NULL for the global list.
   */
  private ?OutboundSubscriptionInterface $subscription = NULL;

  /**
   * Constructs an OutboundDeliveryLogListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
    );
  }

  /**
   * Sets the subscription context to filter delivery logs.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The subscription whose logs to display.
   */
  public function setSubscription(OutboundSubscriptionInterface $subscription): void {
    $this->subscription = $subscription;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function load(): array {
    $query = $this->storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('created', 'DESC');

    if ($this->subscription !== NULL) {
      $query->condition('subscription', $this->subscription->id());
    }

    $ids = $query->execute();

    return $this->storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['subscription'] = $this->t('Subscription');
    $header['entity_type'] = $this->t('Entity Type');
    $header['entity_id'] = $this->t('Entity ID');
    $header['event'] = $this->t('Event');
    $header['status'] = $this->t('Status');
    $header['attempt'] = $this->t('Attempt');
    $header['http_status'] = $this->t('HTTP Status');
    $header['created'] = $this->t('Created');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof OutboundDeliveryLogInterface);

    $row['subscription'] = $entity->getSubscriptionId();
    $row['entity_type'] = $entity->getSourceEntityType();
    $row['entity_id'] = $entity->getSourceEntityId();
    $row['event'] = $entity->getEvent();
    $row['status'] = $entity->getStatus();
    $row['attempt'] = $entity->getAttempt() . ' / ' . $entity->getMaxAttempts();
    $row['http_status'] = $entity->getHttpStatus() ?? $this->t('—');
    $row['created'] = $this->dateFormatter->format($entity->getCreatedTime(), 'short');

    return $row + parent::buildRow($entity);
  }

}
