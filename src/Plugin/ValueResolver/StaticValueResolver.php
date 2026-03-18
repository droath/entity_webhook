<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\ValueResolver;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_webhook\Attribute\ValueResolver;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Returns a fixed value regardless of the payload content.
 */
#[ValueResolver(
  id: 'static_value',
  label: new TranslatableMarkup('Static Value'),
  description: new TranslatableMarkup('Sets a fixed value for the field, independent of the payload.'),
)]
class StaticValueResolver extends ValueResolverBase {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'value' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(array $payload): mixed {
    return $this->configuration['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Value'),
      '#description' => new TranslatableMarkup('The static value to assign to this field.'),
      '#default_value' => $this->configuration['value'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['value'] = $form_state->getValue('value');
  }
}
