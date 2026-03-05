<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a WebhookVerification plugin attribute.
 *
 * Webhook verification plugins authenticate incoming webhook requests.
 * Plugin namespace: Plugin\WebhookVerification
 *
 * @see \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationInterface
 * @see \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManager
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class WebhookVerification extends Plugin {
  /**
   * Constructs a WebhookVerification plugin attribute.
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
