<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines the interface for the field value mutation plugin manager.
 */
interface FieldValueMutationManagerInterface extends PluginManagerInterface {
  /**
   * Returns an options array of plugin IDs to human-readable labels.
   *
   * @return array<string, string>
   *   Keyed by plugin ID, valued by label string.
   */
  public function getOptions(): array;
}
