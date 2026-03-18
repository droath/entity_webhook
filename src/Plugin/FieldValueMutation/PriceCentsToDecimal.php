<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\FieldValueMutation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\FieldValueMutation;

/**
 * Converts cent values to decimal by dividing by 100.
 */
#[FieldValueMutation(
  id: 'price_cents_to_decimal',
  label: new TranslatableMarkup('Price: Cents to Decimal'),
  description: new TranslatableMarkup('Divides by 100 to convert cent values to decimal prices.'),
)]
class PriceCentsToDecimal extends FieldValueMutationBase {
  /**
   * {@inheritdoc}
   */
  public function mutate(mixed $value): mixed {
    if (!is_numeric($value)) {
      return $value;
    }

    return round((float) $value / 100, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state): array {
    return $form;
  }
}
