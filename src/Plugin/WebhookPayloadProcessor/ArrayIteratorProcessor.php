<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\WebhookPayloadProcessor;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_webhook\Attribute\WebhookPayloadProcessor;
use Drupal\entity_webhook\Service\JsonPathExtractorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Iterates over a nested array in the payload and enriches each item.
 *
 * Extracts an array from the payload at the configured JSONPath, then for
 * each item copies the root-level fields listed in merge_from_root so that
 * downstream field mappings can reference them without re-traversal.
 */
#[WebhookPayloadProcessor(
  id: 'array_iterator',
  label: new TranslatableMarkup('Array Iterator'),
  description: new TranslatableMarkup('Iterates over a nested array in the payload, yielding one enriched payload per item.'),
)]
class ArrayIteratorProcessor extends PluginBase implements ContainerFactoryPluginInterface, WebhookPayloadProcessorInterface {
  /**
   * Constructs an ArrayIteratorProcessor plugin.
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
    $this->setConfiguration($configuration);
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
    return [
      'path' => '',
      'merge_from_root' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#default_value' => $this->configuration['path'],
      '#description' => $this->t('A JSONPath expression identifying the nested array to iterate over (e.g. <code>$.addresses</code>).'),
      '#required' => TRUE,
    ];

    $mergeFromRoot = implode("\n", (array) $this->configuration['merge_from_root']);

    $form['merge_from_root'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Merge from root'),
      '#default_value' => $mergeFromRoot,
      '#description' => $this->t('Root-level field names to copy into each sub-payload. Enter one field name per line (e.g. <code>email</code>). These fields do not overwrite keys already present on the sub-item.'),
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $path = trim((string) $form_state->getValue('path'));

    if ($path === '') {
      $form_state->setErrorByName('path', $this->t('Path is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $path = trim((string) $form_state->getValue('path'));
    $rawMerge = $form_state->getValue('merge_from_root');
    $mergeFromRoot = is_array($rawMerge)
      ? array_values(array_filter(array_map('trim', $rawMerge)))
      : $this->parseMergeFromRoot((string) $rawMerge);

    $this->setConfiguration([
      'path' => $path,
      'merge_from_root' => $mergeFromRoot,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function process(array $payload, array $config): array {
    $path = $this->configuration['path'];

    if ($path === '') {
      return [];
    }

    $items = $this->jsonPathExtractor->extract($payload, $path);

    if (!is_array($items)) {
      return [];
    }

    $rootFields = $this->resolveRootFields($payload);

    return array_values(array_map(
      fn (mixed $item) => $this->enrichItem($item, $rootFields),
      $items,
    ));
  }

  /**
   * Parses the merge_from_root textarea value into a filtered array.
   *
   * @param string $rawValue
   *   The raw textarea value with one field name per line.
   *
   * @return string[]
   *   An indexed array of trimmed, non-empty field names.
   */
  private function parseMergeFromRoot(string $rawValue): array {
    return array_values(array_filter(
      array_map('trim', explode("\n", $rawValue)),
    ));
  }

  /**
   * Extracts the configured root-level fields from the payload.
   *
   * @param array<string, mixed> $payload
   *   The root-level payload.
   *
   * @return array<string, mixed>
   *   Root field values keyed by field name, skipping missing keys.
   */
  private function resolveRootFields(array $payload): array {
    $mergeFields = (array) $this->configuration['merge_from_root'];
    $rootValues = [];

    foreach ($mergeFields as $field) {
      if (array_key_exists($field, $payload)) {
        $rootValues[$field] = $payload[$field];
      }
    }

    return $rootValues;
  }

  /**
   * Merges root fields into an individual array item.
   *
   * Root fields do NOT overwrite item-level keys that already exist.
   *
   * @param mixed $item
   *   A single item extracted from the nested array.
   * @param array<string, mixed> $rootFields
   *   The root-level fields to merge in.
   *
   * @return array<string, mixed>
   *   The enriched item array.
   */
  private function enrichItem(mixed $item, array $rootFields): array {
    $itemArray = is_array($item) ? $item : [];

    return $itemArray + $rootFields;
  }
}
