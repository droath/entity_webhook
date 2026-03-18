<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\FieldValueMutation;

/**
 * Encodes a value as a JSON string for json_field storage.
 */
#[FieldValueMutation(
  id: 'json_encode',
  label: new TranslatableMarkup('JSON Encode'),
  description: new TranslatableMarkup('Encodes the value as a JSON string for json_field storage.'),
)]
class JsonEncode extends FieldValueMutationBase {
  /**
   * {@inheritdoc}
   */
  public function mutate(mixed $value): mixed {
    if (is_string($value)) {
      return $value;
    }

    return json_encode($value, JSON_THROW_ON_ERROR);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state): array {
    return $form;
  }
}
