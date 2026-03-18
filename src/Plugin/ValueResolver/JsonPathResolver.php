<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\ValueResolver;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_webhook\Attribute\ValueResolver;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_webhook\Service\JsonPathExtractorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extracts a value using a JSONPath expression.
 */
#[ValueResolver(
  id: 'json_path',
  label: new TranslatableMarkup('JSONPath Expression'),
  description: new TranslatableMarkup('Extracts a value using a JSONPath expression.'),
)]
class JsonPathResolver extends ValueResolverBase implements ContainerFactoryPluginInterface {
  /**
   * Constructs a JsonPathResolver plugin.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\entity_webhook\Service\JsonPathExtractorInterface $jsonPathExtractor
   *   The JSONPath extractor service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly JsonPathExtractorInterface $jsonPathExtractor,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_webhook.json_path_extractor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'path' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(array $payload): mixed {
    $path = $this->configuration['path'];

    if ($path === '') {
      return NULL;
    }

    return $this->jsonPathExtractor->extract($payload, $path);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['path'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('JSONPath Expression'),
      '#description' => new TranslatableMarkup('The JSONPath expression to extract a value from the payload (e.g., <code>$.order.id</code>).'),
      '#default_value' => $this->configuration['path'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['path'] = $form_state->getValue('path');
  }
}
