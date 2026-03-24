<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Plugin\OutboundValueResolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook_broadcast\Attribute\OutboundValueResolver;

/**
 * Returns a fixed value regardless of the entity content.
 */
#[OutboundValueResolver(
  id: 'static_value',
  label: new TranslatableMarkup('Static Value'),
  description: new TranslatableMarkup('Sets a fixed value in the payload, independent of the entity.'),
)]
class StaticValueResolver extends OutboundValueResolverBase {

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
  public function resolve(EntityInterface $entity): mixed {
    return $this->configuration['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Value'),
      '#description' => new TranslatableMarkup('The static value to include in the payload for this field.'),
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
