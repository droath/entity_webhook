<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the interface for outbound value resolver plugins.
 *
 * Outbound value resolver plugins produce a value from a Drupal entity for
 * inclusion in an outbound webhook payload. Each outbound field mapping
 * declares which resolver plugin produces its value.
 */
interface OutboundValueResolverInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {

  /**
   * Resolves a value from the given Drupal entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The source entity.
   *
   * @return mixed
   *   The resolved value, or NULL if not resolvable.
   */
  public function resolve(EntityInterface $entity): mixed;

  /**
   * Returns the human-readable label for this resolver plugin.
   *
   * @return string
   *   The plugin label.
   */
  public function label(): string;

}
