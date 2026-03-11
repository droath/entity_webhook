<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Traits;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides reusable label and machine name form elements for config entities.
 */
trait ConfigEntityFormTrait {
  /**
   * Builds label and machine name form elements for config entities.
   *
   * @param string $existsCallback
   *   The callback to check if an ID already exists. Must be a static method
   *   reference in the form 'Fully\Qualified\ClassName::load'.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity being edited.
   *
   * @return array<string, mixed>
   *   Form elements for 'label' and 'id'.
   */
  protected function buildLabelIdElements(string $existsCallback, ConfigEntityInterface $entity): array {
    return [
      'label' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#maxlength' => 255,
        '#default_value' => $entity->label(),
        '#required' => TRUE,
      ],
      'id' => [
        '#type' => 'machine_name',
        '#default_value' => $entity->id(),
        '#machine_name' => [
          'exists' => $existsCallback,
        ],
        '#disabled' => !$entity->isNew(),
      ],
    ];
  }
}
