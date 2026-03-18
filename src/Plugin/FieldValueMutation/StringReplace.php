<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\FieldValueMutation;

/**
 * Replaces occurrences of a string with another string.
 */
#[FieldValueMutation(
  id: 'string_replace',
  label: new TranslatableMarkup('String Replace'),
  description: new TranslatableMarkup('Replace occurrences of a string with another string.'),
)]
class StringReplace extends FieldValueMutationBase {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'search' => '',
      'replace' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mutate(mixed $value): mixed {
    if (!is_string($value)) {
      return $value;
    }

    return str_replace($this->configuration['search'], $this->configuration['replace'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['search'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Search'),
      '#description' => new TranslatableMarkup('The string to search for.'),
      '#default_value' => $this->configuration['search'],
      '#required' => TRUE,
    ];

    $form['replace'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Replace'),
      '#description' => new TranslatableMarkup('The string to replace matches with.'),
      '#default_value' => $this->configuration['replace'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['search'] = $form_state->getValue('search');
    $this->configuration['replace'] = $form_state->getValue('replace');
  }
}
