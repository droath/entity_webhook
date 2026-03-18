<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\FieldValueMutation;

/**
 * Maps source values to target values using a lookup table.
 */
#[FieldValueMutation(
  id: 'map_values',
  label: new TranslatableMarkup('Map Values'),
  description: new TranslatableMarkup('Maps source values to target values using a lookup table.'),
)]
class MapValues extends FieldValueMutationBase {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'mapping' => [],
      'fallback' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mutate(mixed $value): mixed {
    $mapping = $this->configuration['mapping'];
    $fallback = $this->configuration['fallback'];

    if (!is_string($value) && !is_int($value)) {
      return $value;
    }

    $key = (string) $value;

    if (isset($mapping[$key])) {
      return $mapping[$key];
    }

    return $fallback !== '' ? $fallback : $value;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $mapping = $this->configuration['mapping'];
    $mappingText = '';

    foreach ($mapping as $source => $target) {
      $mappingText .= "$source|$target\n";
    }

    $form['mapping_text'] = [
      '#type' => 'textarea',
      '#title' => new TranslatableMarkup('Value Mapping'),
      '#description' => new TranslatableMarkup('One mapping per line in the format <code>source_value|target_value</code>. Example:<br><code>paid|completed</code><br><code>pending|processing</code>'),
      '#default_value' => trim($mappingText),
      '#required' => TRUE,
      '#rows' => 5,
    ];

    $form['fallback'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Fallback Value'),
      '#description' => new TranslatableMarkup('Value to use when no mapping matches. Leave empty to keep the original value.'),
      '#default_value' => $this->configuration['fallback'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $text = (string) $form_state->getValue('mapping_text');
    $this->configuration['mapping'] = $this->parseMappingText($text);
    $this->configuration['fallback'] = (string) $form_state->getValue('fallback');
  }

  /**
   * Parses the pipe-delimited mapping text into a keyed array.
   *
   * @param string $text
   *   The mapping text with one source|target per line.
   *
   * @return array<string, string>
   *   Keyed by source value, valued by target value.
   */
  protected function parseMappingText(string $text): array {
    $mapping = [];
    $lines = array_filter(array_map('trim', explode("\n", $text)));

    foreach ($lines as $line) {
      $parts = explode('|', $line, 2);
      if (count($parts) === 2) {
        $key = trim($parts[0]);
        $target = trim($parts[1]);
        if ($key !== '') {
          $mapping[$key] = $target;
        }
      }
    }

    return $mapping;
  }
}
