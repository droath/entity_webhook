<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FieldValueMutation plugin attribute.
 *
 * Field value mutation plugins transform extracted field values before they
 * are written to entity fields.
 * Plugin namespace: Plugin\FieldValueMutation
 *
 * @see \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface
 * @see \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManager
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FieldValueMutation extends Plugin {
  /**
   * Constructs a FieldValueMutation plugin attribute.
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
