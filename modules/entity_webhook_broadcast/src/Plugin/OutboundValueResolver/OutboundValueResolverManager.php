<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\entity_webhook_broadcast\Attribute\OutboundValueResolver;

/**
 * Provides the outbound value resolver plugin manager.
 *
 * Discovers plugins in the Plugin/OutboundValueResolver subdirectory of any
 * enabled module and supports PHP attribute-based discovery.
 *
 * @see \Drupal\entity_webhook_broadcast\Attribute\OutboundValueResolver
 * @see \Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver\OutboundValueResolverInterface
 */
class OutboundValueResolverManager extends DefaultPluginManager implements OutboundValueResolverManagerInterface {

  /**
   * Constructs a new OutboundValueResolverManager.
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
      'Plugin/OutboundValueResolver',
      $namespaces,
      $module_handler,
      OutboundValueResolverInterface::class,
      OutboundValueResolver::class,
    );

    $this->alterInfo('outbound_value_resolver_info');
    $this->setCacheBackend($cache_backend, 'outbound_value_resolver_plugins');
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
