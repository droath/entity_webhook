<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\FieldValueMutation;

/**
 * Converts date strings to Unix timestamps.
 */
#[FieldValueMutation(
  id: 'timestamp_format',
  label: new TranslatableMarkup('Timestamp Format'),
  description: new TranslatableMarkup('Converts date strings (ISO 8601, etc.) to Unix timestamps.'),
)]
class TimestampFormat extends FieldValueMutationBase {
  /**
   * {@inheritdoc}
   */
  public function mutate(mixed $value): mixed {
    if (empty($value) || !is_string($value)) {
      return NULL;
    }

    $timestamp = strtotime($value);

    return $timestamp !== FALSE ? $timestamp : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state): array {
    return $form;
  }
}
