<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Plugin\PollingProvider;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\entity_webhook_polling\Attribute\PollingProvider;

/**
 * Provides the polling provider plugin manager.
 *
 * Discovers plugins in the Plugin/PollingProvider subdirectory of any enabled
 * module. Supports both PHP attribute-based (D11) and annotation-based (D10)
 * discovery.
 *
 * @see \Drupal\entity_webhook_polling\Attribute\PollingProvider
 * @see \Drupal\entity_webhook_polling\Plugin\PollingProvider\PollingProviderInterface
 */
class PollingProviderManager extends DefaultPluginManager implements PollingProviderManagerInterface {

  /**
   * Constructs a new PollingProviderManager.
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
      'Plugin/PollingProvider',
      $namespaces,
      $module_handler,
      PollingProviderInterface::class,
      PollingProvider::class,
      'Drupal\entity_webhook_polling\Annotation\PollingProvider',
    );

    $this->alterInfo('polling_provider_info');
    $this->setCacheBackend($cache_backend, 'polling_provider_plugins');
  }

}
