<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\ValueResolver;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_webhook\Attribute\ValueResolver;

/**
 * Provides the value resolver plugin manager.
 *
 * Discovers plugins in the Plugin/ValueResolver subdirectory of any
 * enabled module and supports both PHP attribute-based (D11) and annotation-
 * based (D10) discovery.
 *
 * @see \Drupal\entity_webhook\Attribute\ValueResolver
 * @see \Drupal\entity_webhook\Plugin\ValueResolver\ValueResolverInterface
 */
class ValueResolverManager extends DefaultPluginManager implements ValueResolverManagerInterface {
  /**
   * Constructs a new ValueResolverManager.
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
      'Plugin/ValueResolver',
      $namespaces,
      $module_handler,
      ValueResolverInterface::class,
      ValueResolver::class,
      'Drupal\entity_webhook\Annotation\ValueResolver',
    );

    $this->alterInfo('value_resolver_info');
    $this->setCacheBackend($cache_backend, 'value_resolver_plugins');
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
