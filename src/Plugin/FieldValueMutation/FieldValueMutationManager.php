<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_webhook\Attribute\FieldValueMutation;

/**
 * Provides the field value mutation plugin manager.
 *
 * Discovers plugins in the Plugin/FieldValueMutation subdirectory of any
 * enabled module and supports both PHP attribute-based (D11) and annotation-
 * based (D10) discovery.
 *
 * @see \Drupal\entity_webhook\Attribute\FieldValueMutation
 * @see \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface
 */
class FieldValueMutationManager extends DefaultPluginManager implements FieldValueMutationManagerInterface {
  /**
   * Constructs a new FieldValueMutationManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/FieldValueMutation',
      $namespaces,
      $module_handler,
      FieldValueMutationInterface::class,
      FieldValueMutation::class,
      'Drupal\entity_webhook\Annotation\FieldValueMutation',
    );

    $this->alterInfo('field_value_mutation_info');
    $this->setCacheBackend($cache_backend, 'field_value_mutation_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    $options = [];
    foreach ($this->getDefinitions() as $pluginId => $definition) {
      $options[$pluginId] = (string) $definition['label'];
    }
    asort($options);

    return $options;
  }
}
