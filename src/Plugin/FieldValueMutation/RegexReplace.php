<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\FieldValueMutation;

/**
 * Replaces values matching a regular expression pattern.
 *
 * If the configured pattern is invalid or preg_replace encounters an error,
 * the original value is returned unchanged.
 */
#[FieldValueMutation(
  id: 'regex_replace',
  label: new TranslatableMarkup('Regex Replace'),
  description: new TranslatableMarkup('Replace values matching a regular expression pattern.'),
)]
class RegexReplace extends FieldValueMutationBase {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'pattern' => '',
      'replacement' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mutate(mixed $value): mixed {
    if (!is_string($value)) {
      return $value;
    }

    $result = @preg_replace($this->configuration['pattern'], $this->configuration['replacement'], $value);

    return $result ?? $value;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['pattern'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Pattern'),
      '#description' => new TranslatableMarkup('A valid PHP regular expression including delimiters, e.g. <code>/foo/i</code>.'),
      '#default_value' => $this->configuration['pattern'],
      '#required' => TRUE,
    ];

    $form['replacement'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Replacement'),
      '#description' => new TranslatableMarkup('The replacement string. Use <code>$1</code>, <code>$2</code>, etc. for capture groups.'),
      '#default_value' => $this->configuration['replacement'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $pattern = $form_state->getValue('pattern');

    if (!is_string($pattern) || $pattern === '') {
      return;
    }

    if (@preg_match($pattern, '') === FALSE) {
      $form_state->setErrorByName('pattern', new TranslatableMarkup('The pattern %pattern is not a valid regular expression.', ['%pattern' => $pattern]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['pattern'] = $form_state->getValue('pattern');
    $this->configuration['replacement'] = $form_state->getValue('replacement');
  }
}
