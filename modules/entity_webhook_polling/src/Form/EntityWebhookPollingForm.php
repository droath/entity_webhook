<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_polling\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the add/edit form for EntityWebhookPolling config entities.
 */
class EntityWebhookPollingForm extends EntityForm {

  /**
   * Constructs an EntityWebhookPollingForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook_polling\Entity\EntityWebhookPollingInterface $entity */
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
        'exists' => '\Drupal\entity_webhook_polling\Entity\EntityWebhookPolling::load',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    $form['cron_expression'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cron Expression'),
      '#description' => $this->t('Standard cron expression defining the polling schedule (e.g., <code>*/5 * * * *</code> for every 5 minutes).'),
      '#default_value' => $entity->getCronExpression(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['polling_provider'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Polling Provider'),
      '#description' => $this->t('The plugin ID of the polling provider to use.'),
      '#default_value' => $entity->getPollingProvider(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['endpoint_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Webhook Endpoint'),
      '#description' => $this->t('The webhook endpoint payloads will be routed through.'),
      '#options' => $this->getEndpointOptions(),
      '#default_value' => $entity->getEndpointId(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['source_type_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Source Type'),
      '#description' => $this->t('The webhook source type that defines field mappings for this polling config.'),
      '#options' => $this->getSourceTypeOptions(),
      '#default_value' => $entity->getSourceTypeId(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook_polling\Entity\EntityWebhookPolling $entity */
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Polling configuration %label has been created.', ['%label' => $entity->label()])
        : $this->t('Polling configuration %label has been updated.', ['%label' => $entity->label()]),
    );

    $form_state->setRedirectUrl($entity->toUrl('collection'));

    return $status;
  }

  /**
   * Returns an options array of available WebhookEndpoint IDs to labels.
   *
   * @return array<string, string>
   *   Keyed by endpoint ID, valued by label.
   */
  protected function getEndpointOptions(): array {
    $options = [];
    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface[] $endpoints */
    $endpoints = $this->entityTypeManager
      ->getStorage('webhook_endpoint')
      ->loadMultiple();

    foreach ($endpoints as $endpoint) {
      $options[$endpoint->id()] = (string) $endpoint->label();
    }

    return $options;
  }

  /**
   * Returns an options array of available WebhookSourceType IDs to labels.
   *
   * @return array<string, string>
   *   Keyed by source type ID, valued by label.
   */
  protected function getSourceTypeOptions(): array {
    $options = [];
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface[] $sourceTypes */
    $sourceTypes = $this->entityTypeManager
      ->getStorage('webhook_source_type')
      ->loadMultiple();

    foreach ($sourceTypes as $sourceType) {
      $options[$sourceType->id()] = (string) $sourceType->label();
    }

    return $options;
  }

}
