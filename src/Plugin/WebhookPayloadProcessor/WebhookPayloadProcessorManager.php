<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\WebhookPayloadProcessor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_webhook\Attribute\WebhookPayloadProcessor;

/**
 * Provides the webhook payload processor plugin manager.
 *
 * Discovers plugins in the Plugin/WebhookPayloadProcessor subdirectory of any
 * enabled module and supports PHP attribute-based discovery (D11).
 *
 * @see \Drupal\entity_webhook\Attribute\WebhookPayloadProcessor
 * @see \Drupal\entity_webhook\Plugin\WebhookPayloadProcessor\WebhookPayloadProcessorInterface
 */
class WebhookPayloadProcessorManager extends DefaultPluginManager implements WebhookPayloadProcessorManagerInterface {
  /**
   * Constructs a new WebhookPayloadProcessorManager.
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
      'Plugin/WebhookPayloadProcessor',
      $namespaces,
      $module_handler,
      WebhookPayloadProcessorInterface::class,
      WebhookPayloadProcessor::class,
      'Drupal\entity_webhook\Annotation\WebhookPayloadProcessor',
    );

    $this->alterInfo('webhook_payload_processor_info');
    $this->setCacheBackend($cache_backend, 'webhook_payload_processor_plugins');
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
