<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\WebhookVerification;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_webhook\Attribute\WebhookVerification;

/**
 * Provides the webhook verification plugin manager.
 *
 * Discovers plugins in the Plugin/WebhookVerification subdirectory of any
 * enabled module and supports both PHP attribute-based (D11) and annotation-
 * based (D10) discovery.
 *
 * @see \Drupal\entity_webhook\Attribute\WebhookVerification
 * @see \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationInterface
 */
class WebhookVerificationManager extends DefaultPluginManager implements WebhookVerificationManagerInterface {
  /**
   * Constructs a new WebhookVerificationManager.
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
      'Plugin/WebhookVerification',
      $namespaces,
      $module_handler,
      WebhookVerificationInterface::class,
      WebhookVerification::class,
      'Drupal\entity_webhook\Annotation\WebhookVerification',
    );

    $this->alterInfo('webhook_verification_info');
    $this->setCacheBackend($cache_backend, 'webhook_verification_plugins');
  }
}
