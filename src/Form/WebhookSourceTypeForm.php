<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the add/edit form for WebhookSourceType config entities.
 */
class WebhookSourceTypeForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_webhook\Entity\WebhookSourceType::load',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['field_mappings'] = [
      '#type' => 'details',
      '#title' => $this->t('Field Mappings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $fieldMappingsRaw = $form_state->get('field_mappings_count');
    $existingMappings = $entity->get('field_mappings') ?? [];

    if ($fieldMappingsRaw === NULL) {
      $fieldMappingsRaw = max(count($existingMappings), 1);
      $form_state->set('field_mappings_count', $fieldMappingsRaw);
    }

    for ($delta = 0; $delta < $fieldMappingsRaw; $delta++) {
      $form['field_mappings'][$delta] = $this->buildFieldMappingRow($delta, $existingMappings[$delta] ?? []);
    }

    $form['field_mappings']['add_mapping'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add field mapping'),
      '#submit' => ['::addFieldMappingRow'],
      '#ajax' => [
        'callback' => '::fieldMappingsCallback',
        'wrapper' => 'field-mappings-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['field_mappings']['#prefix'] = '<div id="field-mappings-wrapper">';
    $form['field_mappings']['#suffix'] = '</div>';

    $form['verification'] = [
      '#type' => 'details',
      '#title' => $this->t('Verification'),
      '#open' => TRUE,
    ];

    $form['verification']['verification_plugin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification Plugin ID'),
      '#default_value' => $entity->getVerificationPlugin(),
      '#description' => $this->t('The machine name of the verification plugin to use. Leave empty for no verification.'),
    ];

    return $form;
  }

  /**
   * AJAX callback for the field mappings wrapper.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The field mappings element.
   */
  public function fieldMappingsCallback(array &$form, FormStateInterface $form_state): array {
    return $form['field_mappings'];
  }

  /**
   * Submit handler for adding a new field mapping row.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addFieldMappingRow(array &$form, FormStateInterface $form_state): void {
    $count = $form_state->get('field_mappings_count');
    $form_state->set('field_mappings_count', $count + 1);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $entity */
    $entity = $this->entity;

    $this->applyFieldMappings($form_state);

    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Source type %label has been created.', ['%label' => $entity->label()])
        : $this->t('Source type %label has been updated.', ['%label' => $entity->label()]),
    );

    $form_state->setRedirectUrl($entity->toUrl('collection'));

    return $status;
  }

  /**
   * Builds a single field mapping row element.
   *
   * @param int $delta
   *   The row index.
   * @param array<string, mixed> $defaults
   *   Default values for the row.
   *
   * @return array<string, mixed>
   *   The form element array for one row.
   */
  protected function buildFieldMappingRow(int $delta, array $defaults): array {
    return [
      '#type' => 'fieldset',
      '#title' => $this->t('Mapping @num', ['@num' => $delta + 1]),
      'entity_field' => [
        '#type' => 'textfield',
        '#title' => $this->t('Entity Field'),
        '#default_value' => $defaults['entity_field'] ?? '',
        '#required' => FALSE,
      ],
      'json_path' => [
        '#type' => 'textfield',
        '#title' => $this->t('JSONPath Expression'),
        '#default_value' => $defaults['json_path'] ?? '',
        '#required' => FALSE,
      ],
      'is_identifier' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use as identifier'),
        '#default_value' => $defaults['is_identifier'] ?? FALSE,
      ],
    ];
  }

  /**
   * Applies cleaned field mappings from form state to the entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function applyFieldMappings(FormStateInterface $form_state): void {
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceType $entity */
    $entity = $this->entity;
    $rawMappings = $form_state->getValue('field_mappings') ?? [];
    $cleanMappings = [];

    foreach ($rawMappings as $key => $row) {
      if (!is_array($row)) {
        continue;
      }
      $entityField = trim((string) ($row['entity_field'] ?? ''));
      $jsonPath = trim((string) ($row['json_path'] ?? ''));
      if ($entityField === '' && $jsonPath === '') {
        continue;
      }
      $cleanMappings[] = [
        'entity_field' => $entityField,
        'json_path' => $jsonPath,
        'is_identifier' => (bool) ($row['is_identifier'] ?? FALSE),
      ];
    }

    $entity->set('field_mappings', $cleanMappings);
    $entity->set('verification_plugin', trim((string) ($form_state->getValue('verification_plugin') ?? '')));
  }
}
