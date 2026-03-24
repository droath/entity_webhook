<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an OutboundValueResolver plugin attribute.
 *
 * Outbound value resolver plugins extract values from Drupal entities for
 * inclusion in outbound webhook payloads. Each outbound field mapping declares
 * which resolver plugin produces its value.
 * Plugin namespace: Plugin\OutboundValueResolver
 *
 * @see \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverInterface
 * @see \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverManager
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class OutboundValueResolver extends Plugin {

  /**
   * Constructs an OutboundValueResolver plugin attribute.
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
