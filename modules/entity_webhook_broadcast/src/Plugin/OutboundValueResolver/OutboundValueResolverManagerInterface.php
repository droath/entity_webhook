<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines the interface for the outbound value resolver plugin manager.
 */
interface OutboundValueResolverManagerInterface extends PluginManagerInterface {

  /**
   * Returns an options array of plugin IDs to human-readable labels.
   *
   * @return array<string, string>
   *   Keyed by plugin ID, valued by label string.
   */
  public function getOptions(): array;

}
