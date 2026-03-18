<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\ValueResolver;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines the interface for value resolver plugins.
 *
 * Resolver plugins extract field values from webhook payloads. Each field
 * mapping declares which resolver plugin extracts its value, replacing the
 * hardcoded JSONPath-only extraction.
 */
interface ValueResolverInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {
  /**
   * Resolves a field value from the webhook payload.
   *
   * @param array<string, mixed> $payload
   *   The full decoded JSON payload.
   *
   * @return mixed
   *   The resolved value, or NULL if not resolvable.
   */
  public function resolve(array $payload): mixed;

  /**
   * Returns the human-readable label for this resolver plugin.
   *
   * @return string
   *   The plugin label.
   */
  public function label(): string;
}
