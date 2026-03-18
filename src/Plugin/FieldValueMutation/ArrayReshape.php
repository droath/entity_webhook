<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\FieldValueMutation;

/**
 * Reshapes array items by mapping source keys to target keys.
 */
#[FieldValueMutation(
  id: 'array_reshape',
  label: new TranslatableMarkup('Array Reshape'),
  description: new TranslatableMarkup('Reshapes array items by mapping source keys to target keys.'),
)]
class ArrayReshape extends FieldValueMutationBase {
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
  public function mutate(mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }

    $mapping = $this->configuration['mapping'];

    if (empty($mapping)) {
      return $value;
    }

    return array_map(function (mixed $item) use ($mapping): array {
      if (!is_array($item)) {
        return [];
      }

      $reshaped = [];
      foreach ($mapping as $targetKey => $sourceKey) {
        $reshaped[$targetKey] = $item[$sourceKey] ?? NULL;
      }

      return $reshaped;
    }, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $mapping = $this->configuration['mapping'];
    $mappingText = '';

    foreach ($mapping as $target => $source) {
      $mappingText .= "$target|$source\n";
    }

    $form['mapping_text'] = [
      '#type' => 'textarea',
      '#title' => new TranslatableMarkup('Key Mapping'),
      '#description' => new TranslatableMarkup('One mapping per line in the format <code>target_key|source_key</code>. Example:<br><code>product_id|id</code><br><code>product_name|title</code><br><code>qty|quantity</code>'),
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
   *   The mapping text with one target|source per line.
   *
   * @return array<string, string>
   *   Keyed by target key, valued by source key.
   */
  protected function parseMappingText(string $text): array {
    $mapping = [];
    $lines = array_filter(array_map('trim', explode("\n", $text)));

    foreach ($lines as $line) {
      $parts = explode('|', $line, 2);
      if (count($parts) === 2) {
        $target = trim($parts[0]);
        $source = trim($parts[1]);
        if ($target !== '' && $source !== '') {
          $mapping[$target] = $source;
        }
      }
    }

    return $mapping;
  }
}
