<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a WebhookPayloadProcessor plugin attribute.
 *
 * Payload processor plugins transform an incoming webhook payload into one or
 * more payloads before the standard field-mapping pipeline runs. A typical use
 * case is iterating over a nested array so that each item is processed as an
 * independent entity upsert.
 *
 * Plugin namespace: Plugin\WebhookPayloadProcessor
 *
 * @see \Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorInterface
 * @see \Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorManager
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class WebhookPayloadProcessor extends Plugin {
  /**
   * Constructs a WebhookPayloadProcessor plugin attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable label of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A short description of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {
  }

}
