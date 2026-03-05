<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a PollingProvider plugin attribute.
 *
 * Polling provider plugins fetch records from external sources during cron
 * runs. Plugins are discovered from the Plugin/PollingProvider subdirectory
 * of any enabled module.
 *
 * Plugin namespace: Plugin\PollingProvider
 *
 * @see \Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderInterface
 * @see \Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderManager
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PollingProvider extends Plugin {

  /**
   * Constructs a PollingProvider plugin attribute.
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
