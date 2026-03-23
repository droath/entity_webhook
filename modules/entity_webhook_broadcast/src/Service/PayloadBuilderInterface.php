<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;

/**
 * Defines the interface for building outbound webhook payloads.
 *
 * Transforms a Drupal entity into a structured array payload by applying
 * configured OutboundFieldMapping rules and optional FieldValueMutation
 * plugins for each mapped field.
 */
interface PayloadBuilderInterface {

  /**
   * Builds the webhook payload array for the given entity and subscription.
   *
   * Loads all OutboundFieldMapping children for the subscription, reads each
   * mapped entity field value, applies any configured FieldValueMutation
   * plugin, and returns the structured payload keyed by output_key.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose field values are mapped into the payload. For delete
   *   events this is the pre-deletion entity state captured at hook time.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The subscription whose field mappings define the payload structure.
   *
   * @return array<string, mixed>
   *   The structured payload array keyed by output_key.
   */
  public function build(EntityInterface $entity, OutboundSubscriptionInterface $subscription): array;

}
