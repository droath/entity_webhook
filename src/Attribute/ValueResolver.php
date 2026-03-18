<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ValueResolver plugin attribute.
 *
 * Value resolver plugins extract field values from webhook payloads. Each field
 * mapping declares which resolver plugin extracts its value.
 * Plugin namespace: Plugin\ValueResolver
 *
 * @see \Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverInterface
 * @see \Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverManager
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValueResolver extends Plugin {
  /**
   * Constructs a ValueResolver plugin attribute.
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
