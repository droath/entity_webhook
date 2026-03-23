<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\entity_webhook_broadcast\Entity\OutboundDeliveryLogListBuilder;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;

/**
 * Provides title callbacks and page controllers for subscription-related pages.
 */
final class SubscriptionListController extends ControllerBase {

  /**
   * Returns the page title for the subscriptions list.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $outbound_endpoint
   *   The outbound endpoint.
   *
   * @return string
   *   The translated page title including the endpoint label.
   */
  public function getTitle(OutboundEndpointInterface $outbound_endpoint): string {
    return (string) $this->t('Subscriptions for @endpoint', [
      '@endpoint' => $outbound_endpoint->label(),
    ]);
  }

  /**
   * Renders the delivery log list filtered to a single subscription.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $outbound_endpoint
   *   The parent outbound endpoint (used for breadcrumb context).
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $outbound_subscription
   *   The outbound subscription whose delivery logs to display.
   *
   * @return array
   *   A render array for the filtered delivery log list.
   */
  public function deliveryLogs(
    OutboundEndpointInterface $outbound_endpoint,
    OutboundSubscriptionInterface $outbound_subscription,
  ): array {
    $listBuilder = $this->entityTypeManager()
      ->getListBuilder('outbound_delivery_log');

    assert($listBuilder instanceof OutboundDeliveryLogListBuilder);
    $listBuilder->setSubscription($outbound_subscription);

    return $listBuilder->render();
  }

  /**
   * Returns the page title for the delivery logs list of a subscription.
   *
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $outbound_endpoint
   *   The parent outbound endpoint.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $outbound_subscription
   *   The outbound subscription.
   *
   * @return string
   *   The translated page title including the subscription label.
   */
  public function getDeliveryLogsTitle(
    OutboundEndpointInterface $outbound_endpoint,
    OutboundSubscriptionInterface $outbound_subscription,
  ): string {
    return (string) $this->t('Delivery Logs for @subscription', [
      '@subscription' => $outbound_subscription->label(),
    ]);
  }

}
