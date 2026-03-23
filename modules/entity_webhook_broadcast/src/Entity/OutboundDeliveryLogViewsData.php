<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for the OutboundDeliveryLog content entity.
 *
 * Extends the default EntityViewsData handler and enriches field definitions
 * so all delivery log fields appear in Views with meaningful labels and
 * appropriate filter/sort/argument handlers for the admin dashboard.
 */
class OutboundDeliveryLogViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    $table = &$data['outbound_delivery_log'];

    $table['table']['group'] = $this->t('Outbound Delivery Log');
    $table['table']['provider'] = 'entity_webhook_broadcast';

    $this->enrichSubscriptionField($table);
    $this->enrichStatusField($table);
    $this->enrichEventField($table);
    $this->enrichHttpStatusField($table);

    return $data;
  }

  /**
   * Adds filter options for the subscription field.
   *
   * @param array<string, mixed> $table
   *   The Views table data array, passed by reference.
   */
  private function enrichSubscriptionField(array &$table): void {
    if (!isset($table['subscription'])) {
      return;
    }

    $table['subscription']['filter']['id'] = 'string';
    $table['subscription']['argument']['id'] = 'string';
  }

  /**
   * Adds in-operator filter for the status field.
   *
   * @param array<string, mixed> $table
   *   The Views table data array, passed by reference.
   */
  private function enrichStatusField(array &$table): void {
    if (!isset($table['status'])) {
      return;
    }

    $table['status']['filter']['id'] = 'in_operator';
    $table['status']['filter']['options callback'] = [static::class, 'getStatusOptions'];
  }

  /**
   * Adds in-operator filter for the event field.
   *
   * @param array<string, mixed> $table
   *   The Views table data array, passed by reference.
   */
  private function enrichEventField(array &$table): void {
    if (!isset($table['event'])) {
      return;
    }

    $table['event']['filter']['id'] = 'in_operator';
    $table['event']['filter']['options callback'] = [static::class, 'getEventOptions'];
  }

  /**
   * Adds numeric filter for the http_status field.
   *
   * @param array<string, mixed> $table
   *   The Views table data array, passed by reference.
   */
  private function enrichHttpStatusField(array &$table): void {
    if (!isset($table['http_status'])) {
      return;
    }

    $table['http_status']['filter']['id'] = 'numeric';
  }

  /**
   * Returns status options for the Views in-operator filter.
   *
   * @return array<string, string>
   *   Keyed array of status values to human-readable labels.
   */
  public static function getStatusOptions(): array {
    return [
      'pending' => 'Pending',
      'success' => 'Success',
      'failed' => 'Failed',
      'abandoned' => 'Abandoned',
    ];
  }

  /**
   * Returns event options for the Views in-operator filter.
   *
   * @return array<string, string>
   *   Keyed array of event values to human-readable labels.
   */
  public static function getEventOptions(): array {
    return [
      'insert' => 'Insert',
      'update' => 'Update',
      'delete' => 'Delete',
    ];
  }

}
