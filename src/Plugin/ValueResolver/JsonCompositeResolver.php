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
 * Builds a keyed array from multiple JSONPath expressions.
 */
#[ValueResolver(
  id: 'json_composite',
  label: new TranslatableMarkup('JSON Composite'),
  description: new TranslatableMarkup('Builds an object from multiple JSONPath expressions.'),
)]
class JsonCompositeResolver extends ValueResolverBase implements ContainerFactoryPluginInterface {
  /**
   * Constructs a JsonCompositeResolver plugin.
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
      'mapping' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(array $payload): mixed {
    $mapping = $this->configuration['mapping'];

    if (empty($mapping)) {
      return NULL;
    }

    $result = [];
    foreach ($mapping as $key => $path) {
      $result[$key] = $this->jsonPathExtractor->extract($payload, $path);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $mapping = $this->configuration['mapping'];
    $mappingText = '';

    foreach ($mapping as $key => $path) {
      $mappingText .= "$key|$path\n";
    }

    $form['mapping_text'] = [
      '#type' => 'textarea',
      '#title' => new TranslatableMarkup('Key-to-JSONPath Mapping'),
      '#description' => new TranslatableMarkup('One mapping per line in the format <code>key|$.json.path</code>. Example:<br><code>subtotal|$.subtotal_price</code><br><code>tax|$.total_tax</code>'),
      '#default_value' => trim($mappingText),
      '#required' => TRUE,
      '#rows' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $text = (string) $form_state->getValue('mapping_text');
    $this->configuration['mapping'] = $this->parseMappingText($text);
  }

  /**
   * Parses the pipe-delimited mapping text into a keyed array.
   *
   * @param string $text
   *   The mapping text with one key|path per line.
   *
   * @return array<string, string>
   *   Keyed by target key, valued by JSONPath expression.
   */
  protected function parseMappingText(string $text): array {
    $mapping = [];
    $lines = array_filter(array_map('trim', explode("\n", $text)));

    foreach ($lines as $line) {
      $parts = explode('|', $line, 2);
      if (count($parts) === 2) {
        $key = trim($parts[0]);
        $path = trim($parts[1]);
        if ($key !== '' && $path !== '') {
          $mapping[$key] = $path;
        }
      }
    }

    return $mapping;
  }
}
