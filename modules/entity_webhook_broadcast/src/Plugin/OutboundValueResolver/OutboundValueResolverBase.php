<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides a base class for outbound value resolver plugins.
 *
 * Concrete plugins should extend this class and implement resolve() and
 * the form methods (buildConfigurationForm, validateConfigurationForm,
 * submitConfigurationForm) as needed.
 */
abstract class OutboundValueResolverBase extends PluginBase implements OutboundValueResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    $definition = $this->getPluginDefinition();

    return (string) ($definition['label'] ?? $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

}
