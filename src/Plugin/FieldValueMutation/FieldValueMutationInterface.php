<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines the interface for field value mutation plugins.
 *
 * Mutation plugins transform an extracted field value before it is written to
 * an entity field. Multiple plugins can be chained per field mapping.
 */
interface FieldValueMutationInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {
  /**
   * Transforms the given value and returns the mutated result.
   *
   * @param mixed $value
   *   The extracted field value to transform.
   *
   * @return mixed
   *   The transformed value.
   */
  public function mutate(mixed $value): mixed;

  /**
   * Returns the human-readable label for this mutation plugin.
   *
   * @return string
   *   The plugin label.
   */
  public function label(): string;
}
